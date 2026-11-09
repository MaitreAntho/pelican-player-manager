<?php

namespace MaitreAntho\PlayerManager;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Panel;

class PlayerManagerPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'player-manager';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverPages(plugin_path($this->getId(), "src/Filament/$id/Pages"), "MaitreAntho\\PlayerManager\\Filament\\$id\\Pages");
    }

    public function boot(Panel $panel): void {}

    public function getSettingsFormData(): array
    {
        return [
            'host' => (string) config('player-manager.host', ''),
            'history' => (bool) config('player-manager.history', true),
            'retention_days' => (int) config('player-manager.retention_days', 30),
            'refresh' => (int) config('player-manager.refresh', 15),
        ];
    }

    public function getSettingsForm(): array
    {
        $t = fn (string $key) => trans("player-manager::strings.settings.$key");

        return [
            TextInput::make('host')->label($t('host'))->helperText($t('host_help'))->maxLength(255),
            TextInput::make('refresh')->label($t('refresh'))->helperText($t('refresh_help'))->integer()->minValue(5)->maxValue(300)->suffix('s')->required(),
            Toggle::make('history')->label($t('history'))->helperText($t('history_help')),
            TextInput::make('retention_days')->label($t('retention'))->integer()->minValue(1)->maxValue(365)->suffix($t('days'))->required(),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'PLAYER_MANAGER_HOST' => trim((string) ($data['host'] ?? '')),
            'PLAYER_MANAGER_HISTORY' => !empty($data['history']) ? 'true' : 'false',
            'PLAYER_MANAGER_RETENTION_DAYS' => (int) $data['retention_days'],
            'PLAYER_MANAGER_REFRESH' => (int) $data['refresh'],
        ]);

        Notification::make()->title(trans('player-manager::strings.settings.saved'))->success()->send();
    }
}
