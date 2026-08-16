<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FinalizedInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;

    public string $clientName;

    public float $grandTotal;

    public $dueDate;

    private string $pdfBinary;

    private string $pdfFilename;

    public function __construct(Invoice $invoice, string $clientName, float $grandTotal, $dueDate, string $pdfBinary, string $pdfFilename)
    {
        $this->invoice = $invoice;
        $this->clientName = $clientName;
        $this->grandTotal = $grandTotal;
        $this->dueDate = $dueDate;
        $this->pdfBinary = $pdfBinary;
        $this->pdfFilename = $pdfFilename;
    }

    public function build()
    {
        return $this->subject('Invoice INV' . $this->invoice->id)
            ->view('emails.finalizedInvoiceMail', [
                'invoice' => $this->invoice,
                'clientName' => $this->clientName,
                'grandTotal' => $this->grandTotal,
                'dueDate' => $this->dueDate,
            ])
            ->attachData($this->pdfBinary, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
