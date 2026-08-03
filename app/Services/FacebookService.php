<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookService
{
    protected string $version = 'v22.0';
    protected string $baseUrl = 'https://graph.facebook.com';

    /**
     * Get configured HTTP client.
     */
    private function getClient()
    {
        $client = Http::timeout(25);
        if (app()->environment(['local', 'development'])) {
            $client = $client->withoutVerifying();
        }
        return $client;
    }

    /**
     * Get Facebook OAuth redirect URL.
     */
    public function getAuthUrl(): string
    {
        $appId = Setting::getValue('facebook_app_id');
        $redirectUri = route('facebook.callback');
        $scopes = [
            'email',
            'public_profile',
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_metadata',
            'leads_retrieval',
            'ads_read',
            'ads_management',
            'business_management'
        ];

        if (blank($appId)) {
            return '';
        }

        return "https://www.facebook.com/{$this->version}/dialog/oauth?" . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'response_type' => 'code'
        ]);
    }

    /**
     * Exchange authorization code for User Access Token.
     */
    public function getAccessTokenFromCode(string $code): ?array
    {
        $appId = Setting::getValue('facebook_app_id');
        $appSecret = Setting::getValue('facebook_app_secret');
        $redirectUri = route('facebook.callback');

        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/oauth/access_token", [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code
            ]);

            if ($response->failed()) {
                Log::error('FB OAuth Token exchange failed', ['response' => $response->json()]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('FB OAuth Exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Convert short-lived access token to long-lived (60 days) token.
     */
    public function getLongLivedToken(string $shortLivedToken): ?string
    {
        $appId = Setting::getValue('facebook_app_id');
        $appSecret = Setting::getValue('facebook_app_secret');

        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $shortLivedToken
            ]);

            if ($response->failed()) {
                Log::error('FB Exchange token failed', ['response' => $response->json()]);
                return null;
            }

            return $response->json()['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('FB Exchange exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Retrieve User profile details.
     */
    public function getUserProfile(string $token): ?array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/me", [
                'fields' => 'id,name,email',
                'access_token' => $token
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('FB Get Profile Exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get Pages associated with connected account.
     */
    public function getPages(string $token): array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/me/accounts", [
                'fields' => 'id,name,access_token,category,tasks',
                'access_token' => $token,
                'limit' => 100
            ]);

            return $response->successful() ? ($response->json()['data'] ?? []) : [];
        } catch (\Throwable $e) {
            Log::error('FB Get Pages Exception', ['msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Register app to monitor leads webhooks for a Facebook Page.
     */
    public function subscribePageToWebhook(string $pageId, string $pageToken): bool
    {
        try {
            $response = $this->getClient()->post("{$this->baseUrl}/{$this->version}/{$pageId}/subscribed_apps", [
                'subscribed_fields' => 'leadgen',
                'access_token' => $pageToken
            ]);

            return $response->successful() && ($response->json()['success'] ?? false);
        } catch (\Throwable $e) {
            Log::error("FB Subscribe Page Webhook Exception [{$pageId}]", ['msg' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get Lead Forms under a Page.
     */
    public function getLeadForms(string $pageId, string $pageToken): array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/{$pageId}/leadgen_forms", [
                'fields' => 'id,name,status,created_time',
                'access_token' => $pageToken,
                'limit' => 100
            ]);

            return $response->successful() ? ($response->json()['data'] ?? []) : [];
        } catch (\Throwable $e) {
            Log::error("FB Get Lead Forms Exception [{$pageId}]", ['msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get Leads generated by a specific Form.
     */
    public function getLeads(string $formId, string $pageToken): array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/{$formId}/leads", [
                'fields' => 'id,created_time,field_data,ad_id,ad_name,campaign_id,campaign_name,platform',
                'access_token' => $pageToken,
                'limit' => 100
            ]);

            return $response->successful() ? ($response->json()['data'] ?? []) : [];
        } catch (\Throwable $e) {
            Log::error("FB Get Leads Exception [{$formId}]", ['msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get detailed info for a single Leadgen ID.
     */
    public function getSingleLead(string $leadId, string $pageToken): ?array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/{$leadId}", [
                'fields' => 'id,created_time,field_data,ad_id,ad_name,campaign_id,campaign_name,platform',
                'access_token' => $pageToken
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error("FB Get Single Lead Exception [{$leadId}]", ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get associated Ad Accounts.
     */
    public function getAdAccounts(string $token): array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/me/adaccounts", [
                'fields' => 'id,name,account_id,account_status',
                'access_token' => $token,
                'limit' => 100
            ]);

            return $response->successful() ? ($response->json()['data'] ?? []) : [];
        } catch (\Throwable $e) {
            Log::error('FB Get Ad Accounts Exception', ['msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get Ad Campaigns under an Ad Account.
     */
    public function getCampaigns(string $adAccountId, string $token): array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/{$adAccountId}/campaigns", [
                'fields' => 'id,name,status,objective',
                'access_token' => $token,
                'limit' => 100
            ]);

            return $response->successful() ? ($response->json()['data'] ?? []) : [];
        } catch (\Throwable $e) {
            Log::error("FB Get Campaigns Exception [{$adAccountId}]", ['msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get Ad Sets under a Campaign.
     */
    public function getAdsets(string $campaignId, string $token): array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/{$campaignId}/adsets", [
                'fields' => 'id,name,status,campaign_id',
                'access_token' => $token,
                'limit' => 100
            ]);

            return $response->successful() ? ($response->json()['data'] ?? []) : [];
        } catch (\Throwable $e) {
            Log::error("FB Get Adsets Exception [{$campaignId}]", ['msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get Ads under an Ad Set.
     */
    public function getAds(string $adsetId, string $token): array
    {
        try {
            $response = $this->getClient()->get("{$this->baseUrl}/{$this->version}/{$adsetId}/ads", [
                'fields' => 'id,name,status,adset_id',
                'access_token' => $token,
                'limit' => 100
            ]);

            return $response->successful() ? ($response->json()['data'] ?? []) : [];
        } catch (\Throwable $e) {
            Log::error("FB Get Ads Exception [{$adsetId}]", ['msg' => $e->getMessage()]);
            return [];
        }
    }
}
