<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\Google\GoogleContactsService;

class ClientObserver
{
    public function __construct(
        private GoogleContactsService $googleContacts,
    ) {}

    public function saved(Client $client): void
    {
        if (! $client->wasRecentlyCreated
            && ! $client->wasChanged(['name', 'phone'])
        ) {
            return;
        }

        $this->googleContacts->syncClientQuietly($client);
    }
}
