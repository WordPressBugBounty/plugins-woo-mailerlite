<?php

abstract class WooMailerLiteAbstractJob
{
    /**
     * Whether the job runs in serial mode (not used directly here).
     */
    private $serial = true;

    /**
     * Delay in seconds before executing the job.
     */
    protected static $delay = 0;

    /**
     * Holds the job record model from the database.
     */
    public static $jobModel;

    /**
     * Used to skip retry logic if needed.
     */
    protected $retryDelay = 10;

    /**
     * Max retry attempts.
     */
    protected $maxRetries = 0;

    protected $resourceLimit = 100;

    /**
     * Each job must implement this method.
     */
    abstract public function handle($data = []);

    /**
     * Returns a new instance of the job.
     */
    public static function getInstance()
    {
        return new static();
    }

    /**
     * Dispatch the job to Action Scheduler or run it synchronously.
     */
    public static function dispatch(array $data = []): void
    {
        $jobClass = static::class;
        $objectId = 0;

        if ((isset($data['selfMechanism']['sync']) && !$data['selfMechanism']['sync']) && class_exists('ActionScheduler')) {
            if (!as_has_scheduled_action($jobClass)) {
                $objectId = as_enqueue_async_action($jobClass, $data);
            }
        }

        static::$jobModel = WooMailerLiteJob::firstOrCreate(
            ['job' => $jobClass],
            ['object_id' => $objectId, 'data' => $data]
        );

        if (isset($data['selfMechanism']['sync'])) {
            static::getInstance()->runSafely($data);
        }
    }

    /**
     * Force synchronous execution.
     */
    public static function dispatchSync(array $data = []): void
    {
        $data['selfMechanism']['sync'] = true;
        static::dispatch($data);
    }

    public function runSafely($data = [])
    {
        try {
            if (!static::$jobModel) {
                static::$jobModel = WooMailerLiteJob::where('job', static::class)->first();
            }
            $this->handle($data);
            if (static::$jobModel) {
                static::$jobModel->delete();
            }
            return true;
        } catch (Throwable $th) {
            WooMailerLiteLog()->error("Failed Job " . static::class, [$th->getMessage()]);

            // retry mechanism
            $attempts = static::$jobModel->data['attempts'] ?? 0;
            static::$jobModel->update([
                'data' => [
                    'status' => 'failed',
                    'error' => $th->getMessage(),
                    'attempts' => $attempts + 1,
                ]
            ]);
            if ($attempts < $this->maxRetries) {
                if (!isset($data['selfMechanism']['sync']) && class_exists('ActionScheduler')) {
                    as_enqueue_async_action(static::class, $data);
                }
            }
        }
    }

    /**
     * Set a delay before job runs.
     */
    public static function delay(int $delay)
    {
        static::$delay = $delay;
        return new static();
    }
}
