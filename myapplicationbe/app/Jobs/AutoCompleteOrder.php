<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoCompleteOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Order $order
    ) {}

    /**
     * Execute the job.
     *
     * Dipanggil 1 menit setelah pembayaran dikonfirmasi.
     * Hanya ubah status jika masih 'confirmed' (belum diubah manual/dibatalkan).
     */
    public function handle(): void
    {
        // Reload from DB to get latest status
        $this->order->refresh();

        if ($this->order->isConfirmed()) {
            $this->order->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
