<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $messageBody = '',
    ) {}

    public function envelope(): Envelope
    {
        $tipo = $this->order->document_type;
        $numero = $this->order->full_number;

        return new Envelope(
            subject: "{$tipo} {$numero} - Restaurante Barrio Peruano",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->order->pdf_path) {
            $attachments[] = Attachment::fromUrl($this->order->pdf_path)
                ->as("{$this->order->full_number}.pdf")
                ->withMime('application/pdf');
        }

        if ($this->order->xml_path) {
            $attachments[] = Attachment::fromUrl($this->order->xml_path)
                ->as("{$this->order->full_number}.xml")
                ->withMime('application/xml');
        }

        return $attachments;
    }
}
