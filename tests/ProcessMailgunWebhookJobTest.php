<?php

namespace Spatie\MailcoachMailgunFeedback\Tests;

use Spatie\Mailcoach\Enums\CampaignSendFeedbackType;
use Spatie\Mailcoach\Models\CampaignSend;
use Spatie\Mailcoach\Models\CampaignSendFeedbackItem;
use Spatie\MailcoachMailgunFeedback\ProcessMailgunWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessMailgunWebhookJobTest extends TestCase
{
    /** @var \Spatie\WebhookClient\Models\WebhookCall */
    private $webhookCall;

    /** @var \Spatie\Mailcoach\Models\CampaignSend */
    private $campaignSend;

    public function setUp(): void
    {
        parent::setUp();

        $this->webhookCall = WebhookCall::create([
            'name' => 'mailgun',
            'payload' => $this->getStub('webhookContent'),
        ]);

        $this->campaignSend = factory(CampaignSend::class)->create([
            'transport_message_id' => '20130503192659.13651.20287@mg.craftremote.com',
        ]);
    }

    /** @test */
    public function it_processes_a_mailgun_webhook_call()
    {
        $job = new ProcessMailgunWebhookJob($this->webhookCall);

        $job->handle();

        $this->assertEquals(1, CampaignSendFeedbackItem::count());
        $this->assertEquals(CampaignSendFeedbackType::BOUNCE, CampaignSendFeedbackItem::first()->type);
        $this->assertTrue($this->campaignSend->is(CampaignSendFeedbackItem::first()->campaignSend));
    }

    /** @test */
    public function it_only_saves_when_event_is_a_failure()
    {
        $data =$this->webhookCall->payload;
        $data['event-data']['event'] = 'success';

        $this->webhookCall->update([
            'payload' => $data,
        ]);

        $job = new ProcessMailgunWebhookJob($this->webhookCall);

        $job->handle();

        $this->assertEquals(0, CampaignSendFeedbackItem::count());
    }

    /** @test */
    public function it_does_nothing_when_it_cannot_find_the_transport_message_id()
    {
        $data = $this->webhookCall->payload;
        $data['event-data']['message']['headers']['message-id'] = 'some-other-id';

        $this->webhookCall->update([
            'payload' => $data,
        ]);

        $job = new ProcessMailgunWebhookJob($this->webhookCall);

        $job->handle();

        $this->assertEquals(0, CampaignSendFeedbackItem::count());
    }
}
