<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ZipFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 
     * File information 
     * [
     *      file_id     => int,
     *      file_md5    => string,
     *      file_name   => string
     * ]
     * @var array $fileData 
     */
    public $fileData;

     /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;
    
    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 660;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($fileData)
    {
        $this->fileData = $fileData;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array
     */
    public function backoff()
    {
        return [3, 10, 20];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Get file from S3
        $file_content = Storage::get("plain/{$this->fileData['file_md5']}");
        
        // Zip file locally
        $zip = new \ZipArchive;
        $zipFilePath = storage_path('app/tmp.zip');
        if ($zip->open($zipFilePath, \ZipArchive::CREATE) === true) {
            $zip->addFromString($this->fileData['file_name'], $file_content);
        }
        $zip->close();

        // Get new file size
        $new_file_size = Storage::disk('local')->size('tmp.zip');

        // Send zipped file to S3/zipped
        Storage::putFileAs('zipped', new File($zipFilePath), $this->fileData['file_md5']);

        // Remove file from S3/plain
        Storage::delete("plain/{$this->fileData['file_md5']}");

        // Trigger message to update file status in "Instashare Admin"
        UpdateFileStatus::dispatch([
            'file_id'       => $this->fileData['file_id'],
            'file_md5'      => $this->fileData['file_md5'],
            'file_name'     => $this->fileData['file_name'],
            'file_status'   => 'ZIPPED',
            'file_size'     => $new_file_size 
        ]);
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        // Log error: failed to zip a file
        Log::error('Failed to zip a file after 3 tries', [
            'file_record_id' => $this->fileData['file_id'],
            'file_md5'      => $this->fileData['file_md5'],
            'file_name'     => $this->fileData['file_name'],
            'error_code'    => $exception->getCode(),
            'error_message' => $exception->getMessage()
        ]);

        // Trigger job in "Instashare Admin" to update file status (delete file's record from database and notify user)
        UpdateFileStatus::dispatch([
            'file_id'       => $this->fileData['file_id'],
            'file_md5'      => $this->fileData['file_md5'],
            'file_status'   => 'FAILED',
            'file_size'     => null, 
        ]);
    }
}
