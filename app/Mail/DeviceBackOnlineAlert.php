<?php

namespace App\Mail;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class DeviceBackOnlineAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Device $device;
    public int $downMinutes;

    /**
     * Create a new message instance.
     */
    public function __construct(Device $device, int $downMinutes)
    {
        $this->device = $device;
        $this->downMinutes = $downMinutes;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $deviceName = $this->device->display_name ?: $this->device->device_name ?: 'Unknown Device';

        return new Envelope(
            subject: "[RESOLVED] Time Clock Back Online: {$deviceName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.device-back-online-alert',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
