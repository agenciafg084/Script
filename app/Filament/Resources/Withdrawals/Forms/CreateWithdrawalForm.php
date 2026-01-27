<?php

namespace App\Filament\Resources\Withdrawals\Forms;

use App\Model\Withdrawal;
use App\Providers\PaymentsServiceProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class CreateWithdrawalForm
{
    public static function schema($userId = null): array
    {
        return [
            Select::make('user_id')
                ->label(__('admin.resources.withdrawal.fields.user_id'))
                ->relationship('user', 'username')
                ->searchable()
                ->default($userId)
                ->required()
                ->preload(true),

            Select::make('status')
                ->label(__('admin.resources.withdrawal.fields.status'))
                ->required()
                ->options(self::getTranslatedStatuses())
                ->default(Withdrawal::REQUESTED_STATUS)
                ->rule(function ($get, $state, $set, $context) {
                    return function ($attribute, $value, $fail) use ($context) {
                        $isCreating = $context === 'create';
                        if ($isCreating && $value !== Withdrawal::REQUESTED_STATUS) {
                            $fail(__('admin.resources.withdrawal.helpers.status_creation_rule'));
                        }
                    };
                }),

            TextInput::make('amount')
                ->label(__('admin.resources.withdrawal.fields.amount'))
                ->numeric()
                ->required()
                ->rule(function ($get, $context) {
                    if ($context === 'edit') {
                        return null;
                    }

                    $userId = $get('user_id');
                    $user = \App\Model\User::find($userId);
                    $walletTotal = $user?->wallet?->total ?? 0;

                    return function ($attribute, $value, $fail) use ($walletTotal) {
                        if ($value > $walletTotal) {
                            $fail(__('admin.resources.withdrawal.helpers.amount_overflow'));
                        }
                    };
                }),

            TextInput::make('fee')
                ->label(__('admin.resources.withdrawal.fields.fee'))
                ->numeric()
                ->disabled()
                ->helperText('Fees are auto-calculated, if withdrawal fees are enabled in payment settings.')
                ->helperText(__('admin.resources.withdrawal.helpers.fees_info'))
                ->default(0),

            Textarea::make('message')
                ->label(__('admin.resources.withdrawal.fields.message'))
                ->columnSpanFull(),

            Select::make('payment_method')
                ->label(__('admin.resources.withdrawal.fields.payment_method'))
                ->required()
                ->options(
                    collect(PaymentsServiceProvider::getWithdrawalsAllowedPaymentMethods())
                        ->mapWithKeys(fn ($method) => [$method => $method])
                        ->toArray()
                )
                ->default('Other')
                ->rule(function ($get, $state, $set, $context) {
                    return function ($attribute, $value, $fail) {
                        if ($value === Withdrawal::STRIPE_CONNECT_METHOD) {
                            $fail(__('admin.resources.withdrawal.helpers.stripe_connect_warning'));
                        }
                    };
                }),

            TextInput::make('payment_identifier')
                ->label(__('admin.resources.withdrawal.fields.payment_identifier'))
                ->maxLength(191)
                ->default(null),

            TextInput::make('stripe_payout_id')
                ->label(__('admin.resources.withdrawal.fields.stripe_payout_id'))
                ->maxLength(191)
                ->default(null),

            TextInput::make('stripe_transfer_id')
                ->label(__('admin.resources.withdrawal.fields.stripe_transfer_id'))
                ->maxLength(191)
                ->default(null),

            Toggle::make('processed')
                ->label(__('admin.resources.withdrawal.fields.processed'))
                ->required()
                ->default(0)
                ->disabled(fn ($record) => $record?->processed)
                ->rule(function ($get, $record) {
                    return function ($attribute, $value, $fail) use ($record) {
                        if ($record && $record->processed) {
                            $fail(__('admin.resources.withdrawal.helpers.processed_warning'));
                        }
                    };
                }),
        ];
    }

    public static function getAvailableStatuses(): array
    {
        return [
            Withdrawal::APPROVED_STATUS,
            Withdrawal::REQUESTED_STATUS,
            Withdrawal::REJECTED_STATUS,
        ];
    }

    public static function getTranslatedStatuses(): array
    {
        return collect(self::getAvailableStatuses())
            ->mapWithKeys(fn ($status) => [
                $status => __('admin.resources.withdrawal.status_labels.'.$status),
            ])->toArray();
    }
}
