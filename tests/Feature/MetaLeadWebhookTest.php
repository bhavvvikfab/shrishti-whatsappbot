<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaLeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_lead_webhook_creates_a_database_entry(): void
    {
        config()->set('services.meta_leads.access_token', null);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'leadgen',
                            'value' => [
                                'page_id' => '123456789',
                                'form_id' => '987654321',
                                'leadgen_id' => 'lead-001',
                                'ad_id' => 'ad-001',
                                'adgroup_id' => 'adgroup-001',
                                'campaign_id' => 'campaign-001',
                                'created_time' => '2026-05-19T11:30:00+0000',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/meta-leads/webhook', $payload);

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'saved' => 1,
            ]);

        $this->assertDatabaseHas('meta_lead_entries', [
            'leadgen_id' => 'lead-001',
            'page_id' => '123456789',
            'form_id' => '987654321',
            'status' => 'received',
        ]);
    }
}
