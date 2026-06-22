<?php

namespace App\Livewire\Admin;

use App\Support\WritesAudit;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Models\ApiToken;

#[Layout('components.layouts.app')]
class ApiTokens extends Component
{
    use WritesAudit;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('panel.manage');
    }

    public function createToken(): void
    {
        $this->authorize('panel.manage');
        $this->validate(['name' => 'required|string|max:120']);

        [, $plaintext] = ApiToken::mint($this->name);

        session()->flash('warden_new_token', $plaintext);
        $this->audit('panel.token.create', $this->name);
        $this->name = '';
    }

    public function revoke(int $tokenId): void
    {
        $this->authorize('panel.manage');
        ApiToken::query()->whereKey($tokenId)->delete();
        $this->audit('panel.token.delete', (string) $tokenId);
    }

    public function render()
    {
        return view('livewire.admin.api-tokens', [
            'tokens' => ApiToken::query()->orderByDesc('id')->get(),
        ]);
    }
}
