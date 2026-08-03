# WhatsApp Lead Management CRM - Setup Guide

## Overview

This is a complete WhatsApp Lead Management CRM system built with Laravel and Bootstrap, similar to Kraya AI. It includes lead management, WhatsApp automation, chatbot functionality, campaigns, and analytics.

## Prerequisites

- PHP 8.1 or higher
- Laravel 10.x or higher
- MySQL 5.7 or higher
- Composer
- Node.js and NPM
- WhatsApp Business API account
- OpenAI API key (optional, for AI features)

## Installation Steps

### 1. Database Migrations

Run the following migrations to create the required tables:

```bash
php artisan migrate
```

The following tables will be created:
- `whatsapp_conversations` - Stores WhatsApp conversations
- `whatsapp_messages` - Stores individual messages
- `whatsapp_automation_rules` - Automation rules for auto-replies
- `whatsapp_followups` - Follow-up reminders and tasks
- `lead_notes` - Notes attached to leads
- `lead_tags` - Tags for categorizing leads
- `lead_tag_pivot` - Pivot table for lead-tag relationships
- `whatsapp_campaigns` - Campaign management

### 2. Configuration

#### WhatsApp Configuration

Add the following to your `.env` file:

```env
WHATSAPP_VERIFY_TOKEN=your_verify_token
WHATSAPP_APP_ID=your_app_id
WHATSAPP_APP_SECRET=your_app_secret
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_ACCESS_TOKEN=your_access_token
WHATSAPP_WEBHOOK_URL=https://your-domain.com/whatsapp-configration/webhook
```

Configure WhatsApp in the database via the admin panel or directly:

```bash
php artisan tinker
>>> App\Models\WhatsappConfig::create([
...     'app_id' => 'your_app_id',
...     'app_secret' => 'your_app_secret',
...     'phone_number_id' => 'your_phone_number_id',
...     'business_account_id' => 'your_business_account_id',
...     'access_token' => 'your_access_token',
...     'webhook_url' => 'https://your-domain.com/whatsapp-configration/webhook',
... ]);
```

#### OpenAI Configuration (Optional)

Add to your `.env` file:

```env
OPENAI_API_KEY=your_openai_api_key
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL=gpt-3.5-turbo
```

### 3. Queue Configuration

Configure queues for background job processing:

```bash
php artisan queue:table
php artisan migrate
```

Add to `.env`:

```env
QUEUE_CONNECTION=database
```

Start the queue worker:

```bash
php artisan queue:work
```

### 4. Broadcasting Configuration (Optional)

For real-time updates using Pusher:

Add to `.env`:

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_APP_CLUSTER=mt1
```

Install Pusher PHP SDK:

```bash
composer require pusher/pusher-php-server
```

### 5. Webhook Setup

1. Go to your Meta for Developers dashboard
2. Select your WhatsApp app
3. Configure webhook:
   - URL: `https://your-domain.com/whatsapp-configration/webhook`
   - Verify Token: Use the value from `WHATSAPP_VERIFY_TOKEN`
4. Subscribe to webhook fields:
   - `messages`
   - `message_status`

## Features

### Lead Management

- **Create/Update Leads**: Via API or admin panel
- **Lead Pipeline**: Track leads through stages
- **Lead Notes**: Add notes to leads
- **Lead Tags**: Categorize leads with tags
- **Lead Conversion**: Convert leads to customers

### WhatsApp Chat System

- **Real-time Inbox**: View all WhatsApp conversations
- **Send Messages**: Text and media messages
- **Message Status**: Track sent, delivered, read status
- **Conversation Assignment**: Assign conversations to team members
- **Unread Counters**: Track unread messages

### WhatsApp Automation

- **Keyword Auto-replies**: Trigger responses based on keywords
- **Welcome Messages**: Automatic welcome for new contacts
- **FAQ Automation**: Pre-configured FAQ responses
- **Drip Campaigns**: Scheduled message sequences
- **Follow-up Reminders**: Automated follow-up scheduling

### Campaign Management

- **Broadcast Campaigns**: Send bulk messages
- **Scheduled Campaigns**: Schedule campaigns for later
- **Drip Campaigns**: Automated message sequences
- **Campaign Analytics**: Track delivery and engagement
- **Recipient Filtering**: Target specific lead segments

### Analytics Dashboard

- **Total Leads**: Overall lead count
- **Conversion Rates**: Lead-to-customer conversion
- **Response Rates**: Message response analytics
- **Campaign Performance**: Campaign success metrics
- **Team Performance**: Agent productivity stats

### AI Features (Optional)

- **AI Chatbot**: OpenAI-powered responses
- **Smart Replies**: AI-suggested responses
- **Lead Qualification**: AI-based lead scoring
- **Sentiment Analysis**: Analyze message sentiment
- **Follow-up Suggestions**: AI-generated follow-up ideas

## API Endpoints

### Lead Management API

```
GET    /api/whatsapp/leads              - List all leads
POST   /api/whatsapp/leads              - Create new lead
GET    /api/whatsapp/leads/{lead}       - Get lead details
PUT    /api/whatsapp/leads/{lead}       - Update lead
DELETE /api/whatsapp/leads/{lead}       - Delete lead
POST   /api/whatsapp/leads/{lead}/notes - Add note to lead
POST   /api/whatsapp/leads/{lead}/convert - Convert to customer
GET    /api/whatsapp/leads/stats        - Lead statistics
GET    /api/whatsapp/leads/by-stage     - Leads by stage
```

### WhatsApp Conversations API

```
GET    /api/whatsapp/conversations           - List conversations
GET    /api/whatsapp/conversations/{id}      - Get conversation
POST   /api/whatsapp/conversations/{id}/assign - Assign conversation
PATCH  /api/whatsapp/conversations/{id}/status - Update status
POST   /api/whatsapp/conversations/{id}/read - Mark as read
GET    /api/whatsapp/conversations/{id}/messages - Get messages
```

### WhatsApp Messaging API

```
POST   /api/whatsapp/send-message - Send text message
POST   /api/whatsapp/send-media   - Send media message
```

### Automation API

```
GET    /api/whatsapp/automation          - List automation rules
POST   /api/whatsapp/automation          - Create rule
GET    /api/whatsapp/automation/{rule}   - Get rule
PUT    /api/whatsapp/automation/{rule}   - Update rule
DELETE /api/whatsapp/automation/{rule}   - Delete rule
PATCH  /api/whatsapp/automation/{rule}/toggle - Toggle rule
```

### Campaign API

```
GET    /api/whatsapp/campaigns          - List campaigns
POST   /api/whatsapp/campaigns          - Create campaign
GET    /api/whatsapp/campaigns/{id}     - Get campaign
PUT    /api/whatsapp/campaigns/{id}     - Update campaign
DELETE /api/whatsapp/campaigns/{id}     - Delete campaign
POST   /api/whatsapp/campaigns/{id}/launch - Launch campaign
GET    /api/whatsapp/campaigns/{id}/stats - Campaign stats
```

## Web Routes

### WhatsApp Chat

```
/whatsapp/inbox              - Chat inbox
/whatsapp/inbox/{id}         - Conversation view
```

### Automation

```
/whatsapp/automation         - Automation rules list
/whatsapp/automation/create  - Create automation rule
/whatsapp/automation/{id}    - Edit automation rule
```

### Campaigns

```
/whatsapp/campaigns          - Campaigns list
/whatsapp/campaigns/create   - Create campaign
/whatsapp/campaigns/{id}     - Campaign details
```

### Follow-ups

```
/whatsapp/followups          - Follow-ups list
```

### Analytics

```
/whatsapp/analytics          - Analytics dashboard
```

## Queue Jobs

The system uses Laravel queues for background processing:

- `SendWhatsAppMessageJob` - Sends WhatsApp messages asynchronously
- `ProcessCampaignJob` - Processes campaign message sending
- `SendFollowupMessageJob` - Sends follow-up messages

## Events

Real-time events are broadcast for:

- `WhatsAppMessageReceived` - New message received
- `WhatsAppMessageSent` - Message sent successfully
- `LeadCreated` - New lead created
- `CampaignLaunched` - Campaign started

## Troubleshooting

### Webhook Not Receiving Messages

1. Verify webhook URL is accessible
2. Check verify token matches
3. Ensure webhook is subscribed to required fields
4. Check Laravel logs: `storage/logs/laravel.log`

### Messages Not Sending

1. Verify WhatsApp API credentials
2. Check phone number format (include country code)
3. Ensure access token is valid
4. Check message template approval status

### Queue Jobs Not Processing

1. Ensure queue worker is running: `php artisan queue:work`
2. Check queue configuration in `.env`
3. Verify database queue table exists
4. Check failed jobs: `php artisan queue:failed`

### AI Features Not Working

1. Verify OpenAI API key is set
2. Check API key has sufficient credits
3. Ensure `OPENAI_MODEL` is valid
4. Check network connectivity to OpenAI API

## Security Considerations

1. **API Keys**: Never commit API keys to version control
2. **Webhook Verification**: Always verify webhook authenticity
3. **Rate Limiting**: Implement rate limiting for API endpoints
4. **Input Validation**: All user inputs are validated
5. **Authentication**: Use proper authentication for API endpoints

## Performance Optimization

1. **Database Indexing**: All foreign keys and frequently queried fields are indexed
2. **Queue Processing**: Use queue workers for heavy operations
3. **Caching**: Implement caching for frequently accessed data
4. **Pagination**: Use pagination for large datasets
5. **Lazy Loading**: Use eager loading to prevent N+1 queries

## Support

For issues or questions:
1. Check Laravel logs in `storage/logs/`
2. Review WhatsApp API documentation
3. Check OpenAI API status
4. Verify database connections

## License

This CRM system is built for internal use. Ensure compliance with WhatsApp Business API terms of service and OpenAI usage policies.
