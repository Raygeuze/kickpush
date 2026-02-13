<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinishStripeSetup extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
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
    public function toMail(object $user): MailMessage
    {
            return (new MailMessage)
                ->greeting('Hello again!')
                ->subject('Reminder - Finish setting up your payment details') 

                ->line('It looks like your account is not quite ready to receive payouts yet.')
                ->line('Please finish setting up your account to receive your winnings.')
                ->line("We have completed the first step in processing funds to you but notice you haven't finished setting up your payment details.")
                ->line('The sooner you complete your payment details the sooner we can get the funds to you!')
                ->action('Payment Details', url('/profile/payments/details'))
                ->line('If you have any issues, please contact support.');
    
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
