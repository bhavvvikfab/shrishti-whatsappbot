<?php

namespace App\Http\Controllers;

use App\Models\FacebookAccount;
use App\Models\FacebookPage;
use App\Models\FacebookLeadForm;
use App\Models\FacebookLead;
use App\Models\FacebookCampaign;
use App\Models\FacebookAdset;
use App\Models\FacebookAd;
use App\Models\Setting;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Services\FacebookService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookController extends Controller
{
    protected FacebookService $fbService;

    public function __construct(FacebookService $fbService)
    {
        $this->fbService = $fbService;
    }

    /**
     * Display Facebook settings integration screen.
     */
    public function index()
    {
        $accounts = FacebookAccount::with('pages')->where('user_id', auth()->id())->get();
        $isConnected = $accounts->isNotEmpty();

        $appId = Setting::getValue('facebook_app_id');
        $appSecret = Setting::getValue('facebook_app_secret');
        $hasCredentials = filled($appId) && filled($appSecret);

        return view('settings.facebook', compact('accounts', 'isConnected', 'hasCredentials'));
    }

    /**
     * Redirect user to Facebook Login dialogue.
     */
    public function connect()
    {
        $authUrl = $this->fbService->getAuthUrl();

        if (empty($authUrl)) {
            return back()->with('error', 'Facebook settings incomplete. Please configure App ID first.');
        }

        return redirect($authUrl);
    }

    /**
     * Handle Facebook OAuth Callback.
     */
    public function callback(Request $request)
    {
        if ($request->has('error') || !$request->has('code')) {
            return redirect()->route('facebook.index')->with('error', 'Authorization cancelled or failed.');
        }

        $tokenData = $this->fbService->getAccessTokenFromCode($request->code);
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return redirect()->route('facebook.index')->with('error', 'Failed to exchange OAuth code.');
        }

        $longLivedToken = $this->fbService->getLongLivedToken($tokenData['access_token']);
        $tokenToUse = $longLivedToken ?: $tokenData['access_token'];

        $profile = $this->fbService->getUserProfile($tokenToUse);
        if (!$profile || !isset($profile['id'])) {
            return redirect()->route('facebook.index')->with('error', 'Failed to retrieve Facebook profile.');
        }

        $expiresAt = isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null;

        $account = FacebookAccount::updateOrCreate(
            ['fb_user_id' => $profile['id']],
            [
                'name' => $profile['name'],
                'email' => $profile['email'] ?? null,
                'access_token' => $tokenToUse,
                'token_expires_at' => $expiresAt,
                'user_id' => auth()->id()
            ]
        );

        // Auto-sync pages on connect
        try {
            $this->syncPagesForAccount($account);
        } catch (\Throwable $e) {
            Log::error('Auto-sync pages failed on login', ['error' => $e->getMessage()]);
        }

        return redirect()->route('facebook.index')->with('success', 'Facebook connected and pages imported successfully!');
    }

    /**
     * Disconnect Facebook integration.
     */
    public function disconnect($id)
    {
        $account = FacebookAccount::where('user_id', auth()->id())->findOrFail($id);
        $account->delete();

        return redirect()->route('facebook.index')->with('success', 'Facebook account disconnected successfully.');
    }

    /**
     * Register or unregister lead gen webhook on a Facebook Page.
     */
    public function togglePageSubscription(Request $request, $id)
    {
        $page = FacebookPage::whereHas('account', function($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($id);

        $subscribe = $request->input('subscribe', false);

        if ($subscribe) {
            $success = $this->fbService->subscribePageToWebhook($page->page_id, $page->access_token);
            if ($success) {
                $page->update(['is_subscribed' => true]);
                return response()->json(['success' => true, 'message' => 'Subscribed to leads webhook successfully!']);
            }
            return response()->json(['success' => false, 'message' => 'Failed to register webhook subscription with Facebook. Ensure Page admin permissions.']);
        } else {
            $page->update(['is_subscribed' => false]);
            return response()->json(['success' => true, 'message' => 'Unsubscribed from leads webhook.']);
        }
    }

    /**
     * Trigger manual sync for all pages, forms, campaigns, and ads.
     */
    public function syncAll($id)
    {
        $account = FacebookAccount::where('user_id', auth()->id())->findOrFail($id);

        try {
            $this->syncPagesForAccount($account);
            $this->syncCampaignsAndAds($account);

            return response()->json(['success' => true, 'message' => 'Pages, forms, leads, and campaigns synced successfully!']);
        } catch (\Throwable $e) {
            Log::error('FB Manual Sync failed', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Sync pages for a connected Facebook Account.
     */
    private function syncPagesForAccount(FacebookAccount $account)
    {
        $pages = $this->fbService->getPages($account->access_token);

        foreach ($pages as $p) {
            $fbPage = FacebookPage::updateOrCreate(
                ['page_id' => $p['id']],
                [
                    'facebook_account_id' => $account->id,
                    'name' => $p['name'],
                    'access_token' => $p['access_token'],
                ]
            );

            // Fetch forms and leads
            $this->syncFormsAndLeadsForPage($fbPage);
        }
    }

    /**
     * Sync lead forms and past leads for a Page.
     */
    private function syncFormsAndLeadsForPage(FacebookPage $page)
    {
        $forms = $this->fbService->getLeadForms($page->page_id, $page->access_token);

        foreach ($forms as $f) {
            $fbForm = FacebookLeadForm::updateOrCreate(
                ['form_id' => $f['id']],
                [
                    'facebook_page_id' => $page->id,
                    'name' => $f['name'],
                    'status' => $f['status'] ?? 'ACTIVE',
                    'raw_data' => $f
                ]
            );

            $leads = $this->fbService->getLeads($fbForm->form_id, $page->access_token);
            foreach ($leads as $l) {
                $this->saveAndImportLead($l, $fbForm, $page);
            }
        }

        $page->update(['is_synced' => true]);
    }

    /**
     * Sync campaigns, ad sets, and ads for the account.
     */
    private function syncCampaignsAndAds(FacebookAccount $account)
    {
        $adAccounts = $this->fbService->getAdAccounts($account->access_token);

        foreach ($adAccounts as $adAccount) {
            $campaigns = $this->fbService->getCampaigns($adAccount['id'], $account->access_token);

            foreach ($campaigns as $c) {
                $fbCampaign = FacebookCampaign::updateOrCreate(
                    ['campaign_id' => $c['id']],
                    [
                        'facebook_account_id' => $account->id,
                        'name' => $c['name'],
                        'status' => $c['status'] ?? 'ACTIVE',
                        'objective' => $c['objective'] ?? null,
                        'raw_data' => $c
                    ]
                );

                $adsets = $this->fbService->getAdsets($fbCampaign->campaign_id, $account->access_token);
                foreach ($adsets as $as) {
                    $fbAdset = FacebookAdset::updateOrCreate(
                        ['adset_id' => $as['id']],
                        [
                            'facebook_campaign_id' => $fbCampaign->id,
                            'campaign_id' => $as['campaign_id'],
                            'name' => $as['name'],
                            'status' => $as['status'] ?? 'ACTIVE',
                            'raw_data' => $as
                        ]
                    );

                    $ads = $this->fbService->getAds($fbAdset->adset_id, $account->access_token);
                    foreach ($ads as $ad) {
                        FacebookAd::updateOrCreate(
                            ['ad_id' => $ad['id']],
                            [
                                'facebook_adset_id' => $fbAdset->id,
                                'adset_id' => $ad['adset_id'],
                                'name' => $ad['name'],
                                'status' => $ad['status'] ?? 'ACTIVE',
                                'raw_data' => $ad
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * Map a Facebook lead record, save it to `facebook_leads`, and auto-import into the primary CRM table.
     */
    public function saveAndImportLead(array $leadData, FacebookLeadForm $fbForm, FacebookPage $page): FacebookLead
    {
        $fields = [];
        $email = null;
        $phone = null;
        $name = null;

        foreach ($leadData['field_data'] ?? [] as $field) {
            $fieldName = $field['name'];
            $fieldVal = $field['values'][0] ?? null;
            $fields[$fieldName] = $fieldVal;

            if (in_array(strtolower($fieldName), ['email', 'e-mail'])) {
                $email = $fieldVal;
            } elseif (in_array(strtolower($fieldName), ['phone', 'phone_number', 'mobile', 'contact'])) {
                $phone = $fieldVal;
            } elseif (in_array(strtolower($fieldName), ['full_name', 'name', 'first_name'])) {
                if ($fieldName === 'first_name') {
                    $name = $fieldVal . ' ' . ($name ?? '');
                } else {
                    $name = $fieldVal;
                }
            }
        }

        $fbLead = FacebookLead::updateOrCreate(
            ['lead_id' => $leadData['id']],
            [
                'facebook_lead_form_id' => $fbForm->id,
                'page_id' => $page->page_id,
                'form_id' => $fbForm->form_id,
                'ad_id' => $leadData['ad_id'] ?? null,
                'ad_name' => $leadData['ad_name'] ?? null,
                'campaign_id' => $leadData['campaign_id'] ?? null,
                'campaign_name' => $leadData['campaign_name'] ?? null,
                'platform' => $leadData['platform'] ?? 'fb',
                'field_data' => $fields,
                'full_name' => trim($name ?? 'Facebook Lead'),
                'email' => $email,
                'phone' => $phone,
                'created_time' => isset($leadData['created_time']) ? Carbon::parse($leadData['created_time']) : null,
            ]
        );

        if (!$fbLead->is_imported) {
            // Find or create a Lead Source named "Facebook Ads"
            $leadSource = LeadSource::firstOrCreate(
                ['name' => 'Facebook Ads'],
                ['created_by' => optional($page->account)->user_id ?? 1]
            );

            // Import into the main CRM leads table
            $mainLead = Lead::create([
                'name' => $fbLead->full_name,
                'email' => $fbLead->email,
                'phone' => $fbLead->phone,
                'source' => 'Facebook Ads',
                'lead_source_id' => $leadSource->id,
                'assigned_user_id' => optional($page->account)->user_id ?? 1,
                'notes' => "Facebook Form: {$fbForm->name}\nCampaign: {$fbLead->campaign_name}\nAd: {$fbLead->ad_name}",
            ]);

            $fbLead->update([
                'is_imported' => true,
                'imported_lead_id' => $mainLead->id
            ]);
        }

        return $fbLead;
    }
}
