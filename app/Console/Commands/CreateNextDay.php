<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateNextDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-next-day';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the next day and assign it a random approved topic and increment the topics used count if used multiple times';

    /**
     * Execute the console command.
     */
    public function handle()
    {
	$this->info('------- START CREATE NEXT DAY COMMAND -------');

        $minUsedCount = \App\Models\Topic::where('approved', true)->min('used_count');

        $approvedTopics = \App\Models\Topic::where('approved', true)
                            ->where('used_count', $minUsedCount)
                            ->get();

        if ($approvedTopics->isEmpty()) {
            $this->error('No approved topics available to assign to the new day.');
            return 1;
        }

        // Select a random topic
        $randomTopic = $approvedTopics->random();

        // Create the new day
        $newDay = new \App\Models\Day();
        $newDay->topic_id = $randomTopic->id;
        $newDay->date = now()->toDateTimeString();
        $newDay->save();

        // Increment the used count for the topic
        $randomTopic->used_count += 1;
        $randomTopic->save();

        $this->info('New day created with topic: ' . $randomTopic->topic);


	$this->info('------- END CREATE NEXT DAY COMMAND -------');
        return 0;
    }
}
