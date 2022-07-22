<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateFileStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 
     * File status 
     * [
     *      file_id     => int,
     *      file_md5    => string,
     *      file_name   => string,
     *      file_status => 'ZIPPED' | 'FAILED',
     *      file_size   => int | null
     * ]
     * @var array $fileData 
     */
    public $fileStatus;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($fileStatus)
    {
        $this->fileStatus = $fileStatus;
        $this->onQueue('instashare_admin');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
    }
}
