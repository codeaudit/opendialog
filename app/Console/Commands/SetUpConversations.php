<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenDialogAi\ConversationBuilder\Conversation;
use OpenDialogAi\Core\Graph\DGraph\DGraphClient;

class SetUpConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversations:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sets up some example conversations for the opendialog project';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        if (!$this->confirm('This will clear your local dgraph and all conversations. ' .
            'Are you sure you want to continue?')) {
            $this->info("OK, not running");
            exit;
        }

        $client = app()->make(DGraphClient::class);

        $this->info('Dropping Schema');
        $client->dropSchema();

        $this->info('Init Schema');
        $client->initSchema();

        $this->info('Setting all existing conversations to unpublished');
        Conversation::all()->each(function (Conversation $conversation) {
            $conversation->status = 'validated';
            $conversation->save();
        });

        $this->publishConversation('no_match_conversation', $this->getNoMatchConversation());
        $this->publishConversation('welcome', $this->getWelcomeConversation());

        $this->warn("Conversations created. Please make sure you have messages set up for " .
            "<options=bold>intent.core.NoMatchResponse</> and <options=bold>intent.opendialog.welcome_response intents</>");
    }

    private function publishConversation($name, $model): void
    {
        $this->info("Creating conversation $name");

        /** @var Conversation $conversation */
        $conversation = Conversation::firstOrCreate(
            ['name' => $name],
            [
                'created_at' => now(),
                'updated_at' => now(),
                'status' => 'validated',
                'yaml_validation_status' => 'validated',
                'yaml_schema_validation_status' => 'validated',
                'scenes_validation_status' => 'validated',
                'model_validation_status' => 'validated',
                'notes' => 'auto generated',
                'model' => $model,

            ]
        );

        $this->info("Publishing conversation $name");
        $conversationModel = $conversation->buildConversation();
        $conversation->publishConversation($conversationModel);
    }

    public function getNoMatchConversation()
    {
        return <<<EOT
conversation:
  id: no_match_conversation
  scenes:
    opening_scene:
      intents:
        - u: 
            i: intent.core.NoMatch
        - b: 
            i: intent.core.NoMatchResponse
            completes: true
EOT;
    }

    public function getWelcomeConversation()
    {
        return <<<EOT
conversation:
  id: welcome
  scenes:
    opening_scene:
      intents:
        - u: 
            i: intent.opendialog.welcome
        - b: 
            i: intent.opendialog.welcome_response
            completes: true
EOT;
    }
}