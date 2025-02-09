<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;

class LicenseExpirationNotification extends Notification
{
    use Queueable;

    private const TIMESTAMP = '2025-02-09 05:07:14';
    private const USER = 'maab16';

    public function __construct(private readonly License $license)
    {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'slack'];
    }

    public function toMail($notifiable): MailMessage
    {
        $daysLeft = now()->diffInDays($this->license->valid_until);
        
        return (new MailMessage)
            ->subject('License Expiration Notice')
            ->greeting("Hello {$notifiable->name}")
            ->line("Your license {$this->license->key} will expire in {$daysLeft} days.")
            ->line("Product: {$this->license->product_id}")
            ->line("Current Active Devices: " . $this->license->activations()->where('is_active', true)->count())
            ->line("Expiration Date: {$this->license->valid_until->format('Y-m-d H:i:s')}")
            ->action('Renew License', url("/licenses/{$this->license->key}/renew"))
            ->line('Please renew your license to maintain uninterrupted service.');
    }

    public function toSlack($notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->warning()
            ->content('License Expiration Alert')
            ->attachment(function ($attachment) {
                $attachment
                    ->title('License Details')
                    ->fields([
                        'License Key' => $this->license->key,
                        'Product' => $this->license->product_id,
                        'Expires' => $this->license->valid_until->format('Y-m-d H:i:s'),
                        'Days Left' => now()->diffInDays($this->license->valid_until),
                        'Active Devices' => $this->license->activations()->where('is_active', true)->count()
                    ]);
            });
    }

    public function toArray($notifiable): array
    {
        return [
            'license_key' => $this->license->key,
            'product_id' => $this->license->product_id,
            'expires_at' => $this->license->valid_until->toIso8601String(),
            'days_left' => now()->diffInDays($this->license->valid_until),
            'active_devices' => $this->license->activations()->where('is_active', true)->count(),
            'notification_time' => self::TIMESTAMP,
            'notified_by' => self::USER
        ];
    }
}