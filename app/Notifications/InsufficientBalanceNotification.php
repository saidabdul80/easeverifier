<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InsufficientBalanceNotification extends Notification
{

    protected float $amountNeeded;
    protected float $currentBalance;
    protected string $serviceName;

    /**
     * Create a new notification instance.
     */
    public function __construct(float $amountNeeded, float $currentBalance, string $serviceName)
    {
        $this->amountNeeded = $amountNeeded;
        $this->currentBalance = $currentBalance;
        $this->serviceName = $serviceName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $shortfall = $this->amountNeeded - $this->currentBalance;

        return (new MailMessage)
            ->subject('Insufficient Balance - Action Required')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We noticed that you attempted to perform a verification but your wallet balance is insufficient.')
            ->line('**Service:** ' . $this->serviceName)
            ->line('**Amount Required:** ₦' . number_format($this->amountNeeded, 2))
            ->line('**Current Balance:** ₦' . number_format($this->currentBalance, 2))
            ->line('**Shortfall:** ₦' . number_format($shortfall, 2))
            ->action('Fund Your Wallet', url('/customer/wallet/fund'))
            ->line('Please fund your wallet to continue using our verification services.')
            ->line('Thank you for choosing Easeverifier!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'amount_needed' => $this->amountNeeded,
            'current_balance' => $this->currentBalance,
            'service_name' => $this->serviceName,
        ];
    }
}
