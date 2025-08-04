<?php

class WooMailerLiteAdminGroupController extends WooMailerLiteController
{
    public function createGroup()
    {
        $this->validate([
            'group' => ['required', 'string'],
            'nonce',
        ]);
        $response = $this->apiClient()->createGroup($this->validated['group']);
        return $this->response($response, $response->status);
    }
}