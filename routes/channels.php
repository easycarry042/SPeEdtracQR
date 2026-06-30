<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Per-document staff collaboration feed (internal notes + public posts). Only
// staff who can act on documents may subscribe — citizens never reach this.
Broadcast::channel('documents.{documentId}.staff', function ($user) {
    return $user->can('advance documents')
        || $user->can('assign documents')
        || $user->can('manage system');
});
