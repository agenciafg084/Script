<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\CreateContactMessage;
use App\Filament\Resources\ContactMessages\Pages\EditContactMessage;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\ContactMessage;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components as Forms;

class ContactMessageResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = ContactMessage::class;

    protected static ?int $navigationSort = 22;

    protected static string|UnitEnum|null $navigationGroup = 'ContactMessages';

    public static function getModelLabel(): string
    {
        return __('admin.resources.contact_message.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.contact_message.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    Forms\TextInput::make('email')
                        ->label(__('admin.resources.contact_message.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(191),

                    Forms\TextInput::make('subject')
                        ->label(__('admin.resources.contact_message.fields.subject'))
                        ->required()
                        ->maxLength(191),

                    Forms\Textarea::make('message')
                        ->label(__('admin.resources.contact_message.fields.message'))
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label(__('admin.resources.contact_message.fields.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label(__('admin.resources.contact_message.fields.subject'))
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('email')->label(__('admin.resources.contact_message.fields.email')),
                        TextConstraint::make('subject')->label(__('admin.resources.contact_message.fields.subject')),
                        TextConstraint::make('message')->label(__('admin.resources.contact_message.fields.message')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);

    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'create' => CreateContactMessage::route('/create'),
            'edit' => EditContactMessage::route('/{record}/edit'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewContactMessage::class,
//            Pages\EditContactMessage::class,
        ]);
    }
}
