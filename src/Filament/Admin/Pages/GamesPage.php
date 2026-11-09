<?php

namespace MaitreAntho\PlayerManager\Filament\Admin\Pages;

use App\Models\Egg;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MaitreAntho\PlayerManager\Games\Catalog;
use MaitreAntho\PlayerManager\Models\EggGame;
use MaitreAntho\PlayerManager\Services\PlayerService;

/**
 * Every egg of the panel with the game the plugin recognised, and a way to correct it.
 */
class GamesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-users';

    protected static ?string $slug = 'player-manager';

    protected string $view = 'player-manager::games';

    public static function canAccess(): bool
    {
        return user()?->can('view playerManager') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return trans('player-manager::strings.admin.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.server');
    }

    public function getTitle(): string
    {
        return trans('player-manager::strings.admin.title');
    }

    public function getSubheading(): ?string
    {
        return trans('player-manager::strings.admin.subheading');
    }

    /** @return array<string, mixed>|null */
    protected function gameOf(Egg $egg): ?array
    {
        $override = EggGame::find($egg->id)?->game;
        if ($override === 'none') {
            return null;
        }

        return $override ? Catalog::get($override) : app(PlayerService::class)->detectEgg($egg);
    }

    public function table(Table $table): Table
    {
        $t = fn (string $k) => trans("player-manager::strings.$k");

        return $table
            ->query(Egg::query())
            ->searchable()
            ->columns([
                TextColumn::make('name')->label($t('admin.egg'))->searchable()->sortable()->weight('medium'),
                TextColumn::make('servers_count')->label($t('admin.servers'))->counts('servers')->sortable()->numeric(),
                TextColumn::make('game')->label($t('admin.game'))
                    ->state(fn (Egg $egg) => $this->gameOf($egg)['label'] ?? $t('admin.not_supported'))
                    ->badge()
                    ->color(fn (Egg $egg) => match (true) {
                        !$this->gameOf($egg) => 'gray',
                        ($this->gameOf($egg)['key'] ?? '') === 'steam' => 'info',
                        default => 'success',
                    })
                    ->description(fn (Egg $egg) => EggGame::find($egg->id) ? $t('admin.manual') : null),
                TextColumn::make('players')->label($t('admin.players'))
                    ->state(fn (Egg $egg) => ($g = $this->gameOf($egg)) && !empty($g['query']) ? $t('protocols.' . $g['query'][0]) : '-'),
                TextColumn::make('admin')->label($t('admin.actions'))
                    ->state(fn (Egg $egg) => ($g = $this->gameOf($egg)) && !empty($g['actions'])
                        ? implode(', ', array_map(fn ($a) => trans("player-manager::strings.actions.$a"), array_keys($g['actions'])))
                        : '-')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('used')->label($t('admin.filter'))->options(['used' => $t('admin.filter_used')])->default('used')
                    ->query(fn (Builder $query, array $data) => $data['value'] === 'used' ? $query->has('servers') : $query),
            ])
            ->recordActions([
                Action::make('change')->label($t('admin.change'))->icon('tabler-pencil')->iconButton()
                    ->visible(fn () => user()?->can('update playerManager') ?? false)
                    ->fillForm(fn (Egg $egg) => ['game' => EggGame::find($egg->id)?->game ?? 'auto'])
                    ->schema([
                        Select::make('game')->label($t('admin.game'))
                            ->options(['auto' => $t('settings.auto'), 'none' => $t('settings.none')] + Catalog::labels())
                            ->selectablePlaceholder(false)->native(false)->searchable(),
                    ])
                    ->action(function (Egg $egg, array $data) {
                        $data['game'] !== 'auto'
                            ? EggGame::updateOrCreate(['egg_id' => $egg->id], ['game' => $data['game']])
                            : EggGame::where('egg_id', $egg->id)->delete();
                    }),
            ]);
    }
}
