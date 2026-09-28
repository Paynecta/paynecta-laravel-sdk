<?php

namespace Paynecta\LaravelSdk\Console;

use Illuminate\Console\Command;
use Paynecta\LaravelSdk\PaynectaClient;

/**
 * Set this application up with Paynecta, from the command line.
 *
 * One command rather than a page of instructions ending in "now copy this
 * into your dashboard". That copy step is the one people skip, and skipping
 * it leaves an application taking payments and never hearing what became of
 * them.
 */
class RegisterWebhookCommand extends Command
{
    protected $signature = 'paynecta:register
        {--url= : The address to register. Defaults to this app\'s own webhook route.}
        {--tapflow= : Scope it to one payment page.}
        {--no-installation : Do not also register this app under Installations.}';

    protected $description = 'Register this application\'s webhook address with Paynecta';

    public function handle(PaynectaClient $client): int
    {
        try {
            $who = $client->whoami();
            $this->line('Connected to <info>' . ($who['name'] ?? $who['account_id'] ?? 'your account') . '</info>');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $url = $this->option('url') ?: url(config('paynecta.webhook_path', 'paynecta/webhook'));
        $tapflow = $this->option('tapflow') ?: config('paynecta.tapflow');

        try {
            $saved = $client->webhooks()->register($url, $tapflow ?: null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Paynecta will send payment notices to <info>' . $saved['url'] . '</info>');

        if (! empty($saved['secret'])) {
            $this->newLine();
            $this->line('Put this in your .env, or deliveries will be refused:');
            $this->line('<comment>PAYNECTA_WEBHOOK_SECRET=' . $saved['secret'] . '</comment>');
            $this->newLine();
            // Said plainly. An endpoint that cannot verify is an endpoint a
            // stranger can write payments into, so this package refuses
            // everything until the secret is set.
            $this->line('Until it is set, every delivery is refused rather than trusted.');
        }

        if (! $this->option('no-installation')) {
            try {
                $client->integrations()->register();
                $this->line('Registered under Developers, Installations in your dashboard.');
            } catch (\Throwable $e) {
                // Not a failure of this command. The webhook is set, which
                // is what actually moves money; a missing row in a list is
                // cosmetic.
                $this->warn('Could not record the installation: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
