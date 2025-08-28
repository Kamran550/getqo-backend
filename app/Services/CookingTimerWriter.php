<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;

class CookingTimerWriter
{
    protected function client(): FirestoreClient
    {
        return new FirestoreClient([
            'projectId'  => env('FIREBASE_PROJECT_ID'),
            'keyFilePath'=> base_path(env('FIREBASE_CREDENTIALS', 'storage/app/keys/firebase.json')),
        ]);
    }

    public function create(int $orderId, int $durationSeconds): void
    {
        $this->client()
            ->collection(env('FIREBASE_COOKING_COLLECTION', 'cooking_orders'))
            ->document((string)$orderId)
            ->set([
                'duration' => $durationSeconds, // saniyə
                'ts'       => time(),
            ]);
    }
}
