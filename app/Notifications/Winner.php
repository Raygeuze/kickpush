<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class Winner extends Notification
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
        if($user->can_accept_payouts){
            return (new MailMessage)
                ->greeting('Contratulations, you have won!')
                ->subject('Winner Winner Chicken Dinner!') 
                ->line('It looks like your account is ready to go, you can sit back and relax while we organise getting the funds to you!')
                ->line('It usually takes around 14 days for the funds to reach your bank account.')
                ->action('Track Payment', url('profile/payments'));
        }
        else {
            return (new MailMessage)
                ->greeting('Contratulations, you have won!')
                ->subject('Winner Winner Chicken Dinner!') 

                ->line('It looks like your account is not quite ready to receive payouts yet.')
                ->line('Please finish setting up your account to receive your winnings.')
                ->line('It usually takes around 14 days for the funds to reach your bank account once we have all the information we need from you.')
                ->line('The sooner you complete your payment details the sooner we can get the funds to you!')
                ->action('Payment Details', url('/profile/payments/details'))
                ->line('If you have any issues, please contact support.');

        }
    
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
