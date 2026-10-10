<?php

use App\Events\SalonEventLinked;
use App\Jobs\SendPushNotification;
use App\Models\Event;
use App\Models\Salon;
use App\Models\User;
use App\Services\Push\NotificationSender;
use App\Services\Push\PushMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

it('queues a push when a salon event is linked to the couple', function () {
    Queue::fake();
    $couple = User::factory()->create();
    $event = Event::factory()->for($couple, 'owner')->create([
        'salon_id' => Salon::factory()->create(['name' => 'Gül Salonu'])->id,
        'title' => 'Elif & Emre',
    ]);

    SalonEventLinked::dispatch($event);

    Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->user->is($couple)
        && $job->message->body === 'Gül Salonu etkinliğini ekledi — Elif & Emre'
        && $job->message->data === ['type' => 'event', 'event_id' => (string) $event->id]);
});

it('logs instead of sending until FCM is wired, with a masked phone', function () {
    Log::spy();
    $user = User::factory()->create(['phone' => '+905321112233']);

    app(NotificationSender::class)->send($user, new PushMessage('Yeni zarf', 'Posta kutunda yeni bir zarf var — Elif & Emre'));

    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $context['user'] === '+90532***2233'
        && ! str_contains(json_encode($context), '+905321112233'));
});
