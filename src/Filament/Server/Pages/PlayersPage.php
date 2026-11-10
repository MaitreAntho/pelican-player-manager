<?php

namespace MaitreAntho\PlayerManager\Filament\Server\Pages;

use App\Facades\Activity;
use App\Models\Server;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MaitreAntho\PlayerManager\Games\Catalog;
use MaitreAntho\PlayerManager\Models\Peak;
use MaitreAntho\PlayerManager\Models\PlayerSession;
use MaitreAntho\PlayerManager\Models\ServerSetting;
use MaitreAntho\PlayerManager\Protocols\AuthenticationException;
use MaitreAntho\PlayerManager\Protocols\Player;
use MaitreAntho\PlayerManager\Services\PlayerService;
use Throwable;

class PlayersPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-users';

    protected static ?string $slug = 'players';

    protected static ?int $navigationSort = 4;

    protected string $view = 'player-manager::players';

    public const READ = 'players.read';

    public const MANAGE = 'players.manage';

    public const COMMAND = 'players.command';

    public const CONFIGURE = 'players.configure';

    /** Last command output shown under the table. */
    public ?string $lastOutput = null;

    public ?string $lastCommand = null;

    /** Players read from the game's own list command, on demand. @var array<int, array<string, mixed>> */
    public array $listed = [];

    public ?int $listedAt = null;

    public static function canAccess(): bool
    {
        /** @var Server|null $server */
        $server = Filament::getTenant();

        if (!parent::canAccess() || !$server || !(user()?->can(self::READ, $server) ?? false)) {
            return false;
        }
        $service = app(PlayerService::class);
        if ($service->game($server) !== null) {
            return true;
        }

        // Turned off by hand on this server: whoever can configure keeps the page, to turn it back on.
        return $service->setting($server)?->game === 'none' && (user()?->can(self::CONFIGURE, $server) ?? false);
    }

    /** No game for this server: the page only offers the settings to turn it back on. */
    public function disabled(): bool
    {
        return $this->service()->game($this->server()) === null;
    }

    public static function getNavigationLabel(): string
    {
        return trans('player-manager::strings.title');
    }

    public function getTitle(): string
    {
        return trans('player-manager::strings.title');
    }

    protected function server(): Server
    {
        /** @var Server */
        return Filament::getTenant();
    }

    protected function can(string $permission): bool
    {
        return user()?->can($permission, $this->server()) ?? false;
    }

    protected function service(): PlayerService
    {
        return app(PlayerService::class);
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        return $this->service()->players($this->server());
    }

    // ---------------------------------------------------------------- table

    public function table(Table $table): Table
    {
        $t = fn (string $k) => trans("player-manager::strings.$k");

        return $table
            ->records(fn (?string $search) => $this->rows($search))
            ->poll(fn () => $this->disabled() ? null : max(5, (int) config('player-manager.refresh', 15)) . 's')
            ->paginated(false)
            ->searchable()
            ->emptyStateIcon(fn () => $this->disabled() ? 'tabler-power' : 'tabler-users')
            ->emptyStateHeading(fn () => $this->emptyHeading())
            ->emptyStateDescription(fn () => $this->emptyDescription())
            ->columns([
                TextColumn::make('name')->label($t('columns.name'))->weight('medium')->wrap(),
                TextColumn::make('id')->label($t('columns.id'))->fontFamily('mono')->copyable()->placeholder('-')->toggleable()->visible(fn () => $this->hasColumn('id')),
                TextColumn::make('score')->label($t('columns.score'))->numeric()->placeholder('-')->toggleable()->visible(fn () => $this->hasColumn('score')),
                TextColumn::make('seconds')->label($t('columns.time'))->formatStateUsing(fn ($state) => $state === null ? null : self::duration((int) $state))->placeholder('-')->toggleable()->visible(fn () => $this->hasColumn('seconds')),
                TextColumn::make('ping')->label($t('columns.ping'))->suffix(' ms')->placeholder('-')->toggleable()->visible(fn () => $this->hasColumn('ping')),
            ])
            ->recordActions([
                $this->playerAction('kick', 'tabler-door-exit', 'warning', true),
                $this->playerAction('ban', 'tabler-ban', 'danger', true),
                ActionGroup::make([
                    $this->playerAction('whitelist', 'tabler-user-check', 'gray', false),
                    $this->playerAction('unwhitelist', 'tabler-user-x', 'gray', false),
                    $this->playerAction('op', 'tabler-crown', 'gray', false),
                    $this->playerAction('deop', 'tabler-crown-off', 'gray', false),
                ])->visible(fn () => $this->can(self::MANAGE) && array_intersect(['whitelist', 'op'], $this->service()->actions($this->server()))),
            ])
            ->headerActions([
                $this->listAction(),
                $this->sayAction(),
                $this->unbanAction(),
                $this->commandAction(),
                Action::make('refresh')->label($t('actions.refresh'))->icon('tabler-refresh')->color('gray')->iconButton()
                    ->tooltip($t('actions.refresh'))
                    ->hidden(fn () => $this->disabled())
                    ->action(fn () => $this->service()->players($this->server(), fresh: true)),
                $this->settingsAction(),
            ]);
    }

    /** @return array<string, array<string, mixed>> */
    protected function rows(?string $search): array
    {
        if ($this->disabled()) {
            return [];
        }
        $result = $this->state()['result'];
        $players = $result?->players ?? [];
        // A list asked through the console (games that share no names) stays two minutes.
        if ($this->listedAt && $this->listedAt > time() - 120 && (!$result || !$result->names || !$players)) {
            $players = array_map(fn ($p) => Player::fromArray($p), $this->listed);
        }
        $rows = [];
        foreach ($players as $i => $p) {
            if ($search && !Str::contains(Str::lower($p->name . ' ' . $p->id), Str::lower($search))) {
                continue;
            }
            $rows['p' . $i . '-' . md5($p->name)] = $p->toArray();
        }

        return $rows;
    }

    /** A column is shown only when the game gives that value for at least one player. */
    protected function hasColumn(string $field): bool
    {
        foreach ($this->rows(null) as $row) {
            if (($row[$field] ?? null) !== null && $row[$field] !== '') {
                return true;
            }
        }

        return false;
    }

    protected function emptyHeading(): string
    {
        if ($this->disabled()) {
            return trans('player-manager::strings.disabled_title');
        }
        $state = $this->state();
        if ($state['error']) {
            return trans('player-manager::strings.errors.' . $state['error'] . '_title');
        }
        if ($state['result'] && !$state['result']->names && $state['result']->count() > 0) {
            return trans('player-manager::strings.count_only', ['count' => $state['result']->count()]);
        }

        return trans('player-manager::strings.nobody');
    }

    protected function emptyDescription(): ?string
    {
        if ($this->disabled()) {
            return trans('player-manager::strings.disabled_help');
        }
        $state = $this->state();
        if ($state['error']) {
            $game = $this->service()->game($this->server());
            $hint = isset($game['hint']) ? ' ' . trans('player-manager::strings.hints.' . $game['hint']) : '';

            return trans('player-manager::strings.errors.' . $state['error']) . $hint;
        }
        if ($state['result'] && !$state['result']->names && $state['result']->count() > 0) {
            return trans('player-manager::strings.count_only_help');
        }

        return null;
    }

    // -------------------------------------------------------------- actions

    protected function playerAction(string $action, string $icon, string $color, bool $askReason): Action
    {
        $t = fn (string $k, array $r = []) => trans("player-manager::strings.$k", $r);

        $a = Action::make($action)
            ->label($t("actions.$action"))
            ->icon($icon)
            ->color($color)
            ->visible(fn () => $this->can(self::MANAGE) && in_array($action, $this->service()->actions($this->server()), true))
            ->modalHeading(fn (array $record) => $t("actions.{$action}_heading", ['name' => $record['name']]))
            ->modalSubmitActionLabel($t("actions.$action"))
            ->action(function (array $record, array $data) use ($action) {
                $this->perform($action, $record, (string) ($data['reason'] ?? ''));
            });

        if ($askReason) {
            $a->schema([TextInput::make('reason')->label($t('actions.reason'))->maxLength(200)]);
        } else {
            $a->requiresConfirmation();
        }
        if ($action === 'ban') {
            $a->modalDescription($t('actions.ban_help'));
        }

        return in_array($action, ['kick', 'ban'], true) ? $a->button()->size('sm') : $a;
    }

    /** Asks the game for its own player list, for games whose query gives no names. */
    protected function listAction(): Action
    {
        $t = fn (string $k) => trans("player-manager::strings.$k");

        return Action::make('list')
            ->label($t('actions.list'))
            ->icon('tabler-list-search')
            ->color('gray')
            ->button()
            ->tooltip($t('actions.list_help'))
            ->visible(function () {
                if (!$this->can(self::MANAGE) || empty($this->service()->game($this->server())['list'])) {
                    return false;
                }
                $r = $this->state()['result'];

                return !$r || !$r->names;
            })
            ->action(function () {
                try {
                    $players = $this->service()->detailedPlayers($this->server()) ?? [];
                    $this->listed = array_map(fn ($p) => $p->toArray(), $players);
                    $this->listedAt = time();
                    Notification::make()->title(trans_choice('player-manager::strings.summary.players', count($players), ['count' => count($players)]))->success()->send();
                } catch (Throwable $e) {
                    $this->fail($e);
                }
            });
    }

    protected function sayAction(): Action
    {
        $t = fn (string $k) => trans("player-manager::strings.$k");

        return Action::make('say')
            ->label($t('actions.say'))
            ->icon('tabler-speakerphone')
            ->button()
            ->visible(fn () => $this->can(self::MANAGE) && in_array('say', $this->service()->actions($this->server()), true))
            ->schema([Textarea::make('message')->label($t('actions.message'))->rows(2)->maxLength(250)->required()])
            ->modalSubmitActionLabel($t('actions.send'))
            ->action(fn (array $data) => $this->perform('say', [], (string) $data['message']));
    }

    protected function unbanAction(): Action
    {
        $t = fn (string $k) => trans("player-manager::strings.$k");

        return Action::make('unban')
            ->label($t('actions.unban'))
            ->icon('tabler-lock-open')
            ->color('gray')
            ->button()
            ->visible(fn () => $this->can(self::MANAGE) && in_array('unban', $this->service()->actions($this->server()), true))
            ->schema([TextInput::make('target')->label($t('actions.unban_target'))->helperText($t('actions.unban_help'))->required()->maxLength(100)])
            ->modalSubmitActionLabel($t('actions.unban'))
            ->action(fn (array $data) => $this->perform('unban', ['name' => $data['target'], 'id' => $data['target']]));
    }

    protected function commandAction(): Action
    {
        $t = fn (string $k) => trans("player-manager::strings.$k");

        return Action::make('command')
            ->label($t('actions.command'))
            ->icon('tabler-terminal-2')
            ->color('gray')
            ->button()
            ->visible(fn () => $this->can(self::COMMAND) && !$this->disabled())
            ->modalDescription(fn () => $this->service()->rconTarget($this->server()) ? $t('actions.command_rcon') : $t('actions.command_console'))
            ->schema([TextInput::make('command')->label($t('actions.command'))->required()->maxLength(500)->extraInputAttributes(['class' => 'font-mono'])])
            ->modalSubmitActionLabel($t('actions.run'))
            ->action(function (array $data) {
                try {
                    $r = $this->service()->run($this->server(), $data['command']);
                    $this->log('command', ['command' => $data['command']]);
                    $this->show($data['command'], $r['output']);
                } catch (Throwable $e) {
                    $this->fail($e);
                }
            });
    }

    protected function settingsAction(): Action
    {
        $t = fn (string $k, array $r = []) => trans("player-manager::strings.$k", $r);

        return Action::make('settings')
            ->label($t('settings_title'))
            ->icon('tabler-settings')
            ->color('gray')
            ->iconButton()
            ->tooltip($t('settings_title'))
            ->visible(fn () => $this->can(self::CONFIGURE))
            ->modalWidth(Width::Large)
            ->fillForm(function () {
                $s = $this->service()->setting($this->server());

                return ['game' => $s?->game ?? 'auto', 'query_port' => $s?->query_port, 'rcon_port' => $s?->rcon_port];
            })
            ->schema([
                Placeholder::make('detected')->label($t('settings.detected'))->content(fn () => $this->describe()),
                Select::make('game')->label($t('settings.game'))->options(['auto' => $t('settings.auto'), 'none' => $t('settings.none')] + Catalog::labels())->selectablePlaceholder(false)->native(false)->searchable(),
                TextInput::make('query_port')->label($t('settings.query_port'))->integer()->minValue(1)->maxValue(65535)->placeholder($t('settings.auto')),
                TextInput::make('rcon_port')->label($t('settings.rcon_port'))->integer()->minValue(1)->maxValue(65535)->placeholder($t('settings.auto')),
                TextInput::make('rcon_password')->label($t('settings.rcon_password'))->password()->revealable()->maxLength(255)
                    ->placeholder(fn () => $this->service()->setting($this->server())?->rcon_password ? $t('settings.password_kept') : $t('settings.auto'))
                    ->helperText($t('settings.rcon_help')),
            ])
            ->action(function (array $data) {
                $setting = ServerSetting::firstOrNew(['server_id' => $this->server()->id]);
                $setting->game = $data['game'] !== 'auto' ? $data['game'] : null;
                $setting->query_port = $data['query_port'] ?: null;
                $setting->rcon_port = $data['rcon_port'] ?: null;
                if (filled($data['rcon_password'])) {
                    $setting->rcon_password = $data['rcon_password'];
                }
                $setting->save();
                $this->log('settings', array_filter(['game' => $setting->game, 'query_port' => $setting->query_port, 'rcon_port' => $setting->rcon_port]));
                $this->redirect(static::getUrl());
            })
            ->extraModalFooterActions([
                Action::make('forget_password')->label($t('settings.forget_password'))->color('danger')->link()
                    ->visible(fn () => filled($this->service()->setting($this->server())?->rcon_password))
                    ->action(function () {
                        ServerSetting::where('server_id', $this->server()->id)->update(['rcon_password' => null]);
                        $this->redirect(static::getUrl());
                    }),
            ]);
    }

    /** @param array{name?: string, id?: ?string} $player */
    protected function perform(string $action, array $player, string $text = ''): void
    {
        try {
            $r = $this->service()->act($this->server(), $action, $player, $text);
            $this->log($action, array_filter(['player' => $player['name'] ?? null, 'id' => $player['id'] ?? null, 'text' => $text ?: null]));
            Notification::make()
                ->title(trans("player-manager::strings.done.$action", ['name' => $player['name'] ?? '']))
                ->body($r['via'] === 'console' ? trans('player-manager::strings.done.via_console' . (!empty($r['fallback']) ? '_' . $r['fallback'] : '')) : null)
                ->success()->send();
            $this->show($r['command'], $r['output']);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    protected function show(string $command, string $output): void
    {
        $this->lastCommand = $command;
        $this->lastOutput = trim($output) !== '' ? mb_substr(trim($output), 0, 4000) : trans('player-manager::strings.no_output');
    }

    protected function fail(Throwable $e): void
    {
        report($e);
        Notification::make()
            ->title(trans('player-manager::strings.errors.action_failed'))
            ->body($e instanceof AuthenticationException ? trans('player-manager::strings.errors.auth') : $e->getMessage())
            ->danger()->send();
    }

    /** @param array<string, mixed> $properties */
    protected function log(string $action, array $properties): void
    {
        Activity::event("server:players.$action")->property($properties)->log();
    }

    // ------------------------------------------------------------- page data

    /** What the plugin found for this server, for the settings form. */
    public function describe(): string
    {
        $svc = $this->service();
        $server = $this->server();
        $game = $svc->game($server);
        if ($game === null) {
            return trans('player-manager::strings.settings.none');
        }
        $q = $svc->queryTarget($server);
        $r = $svc->rconTarget($server);
        $t = fn (string $k, array $p = []) => trans("player-manager::strings.$k", $p);

        return implode(' · ', array_filter([
            $game['label'] ?? $t('settings.none'),
            $q ? $t('describe.query', ['protocol' => $t("protocols.{$q['name']}"), 'address' => (string) $q['endpoint']]) : $t('describe.no_query'),
            $r ? $t('describe.rcon', ['protocol' => $t("protocols.{$r['name']}"), 'address' => (string) $r['endpoint']]) : $t('describe.console'),
        ]));
    }

    /** Header line: players, map, source and freshness. */
    public function summary(): ?string
    {
        if ($this->disabled()) {
            return null;
        }
        $state = $this->state();
        $r = $state['result'];
        if (!$r) {
            return null;
        }
        $t = fn (string $k, array $p = []) => trans("player-manager::strings.$k", $p);

        return implode(' · ', array_filter([
            $r->max ? $t('summary.players_max', ['count' => $r->count(), 'max' => $r->max]) : trans_choice('player-manager::strings.summary.players', $r->count(), ['count' => $r->count()]),
            $r->map ? $t('summary.map', ['map' => $r->map]) : null,
            $state['via'] ? $t('summary.via', ['protocol' => $t("protocols.{$state['via']}")]) : null,
        ]));
    }

    public function peakToday(): ?int
    {
        return Peak::query()->where('server_id', $this->server()->id)->where('day', now()->toDateString())->value('peak');
    }

    /** @return Collection<int, array{day: string, peak: int}> */
    public function peaks(): Collection
    {
        $rows = Peak::query()->where('server_id', $this->server()->id)->where('day', '>=', now()->subDays(13)->toDateString())->pluck('peak', 'day');

        return collect(range(13, 0))->map(function ($ago) use ($rows) {
            $day = now()->subDays($ago)->toDateString();

            return ['day' => $day, 'peak' => (int) ($rows[$day] ?? 0)];
        });
    }

    /** @return Collection<int, PlayerSession> */
    public function recent(): Collection
    {
        return PlayerSession::query()->where('server_id', $this->server()->id)
            ->orderByDesc('last_seen_at')->limit(300)->get()
            ->unique('name')->take(25)->values();
    }

    public static function duration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0 ? sprintf('%d h %02d', $h, $m) : sprintf('%d min', max(0, $m));
    }
}
