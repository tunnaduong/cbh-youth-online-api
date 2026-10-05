<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopOrderResource\Pages;
use App\Filament\Resources\ShopOrderResource\RelationManagers;
use App\Models\ShopOrder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShopOrderResource extends Resource
{
  protected static ?string $model = ShopOrder::class;

  protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Forms\Components\Select::make('user_id')
          ->relationship('user', 'username')
          ->required(),
        Forms\Components\TextInput::make('total_amount')
          ->required()
          ->numeric()
          ->prefix('đ'),
        Forms\Components\Select::make('status')
          ->options([
            'pending' => 'Chờ xử lý',
            'processing' => 'Đang xử lý',
            'shipped' => 'Đã giao hàng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
          ])
          ->required()
          ->default('pending'),
        Forms\Components\TextInput::make('phone')
          ->tel()
          ->required(),
        Forms\Components\TextInput::make('shipping_address')
          ->required()
          ->columnSpanFull(),
        // The pin the customer dropped on the map at checkout, as a link
        // (orders from the chat, or from before the map picker, have none).
        Forms\Components\Placeholder::make('maps_url')
          ->label('Vị trí trên bản đồ')
          ->content(fn(?ShopOrder $record) => $record?->maps_url
            ? new \Illuminate\Support\HtmlString(
              '<a href="' . e($record->maps_url) . '" target="_blank" rel="noopener" style="color:#319527;font-weight:600;text-decoration:underline">Mở trong Google Maps</a>'
            )
            : 'Khách chưa chọn vị trí trên bản đồ')
          ->columnSpanFull(),
        Forms\Components\Textarea::make('note')
          ->columnSpanFull(),
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        Tables\Columns\TextColumn::make('id')
          ->label('ID')
          ->sortable(),
        Tables\Columns\TextColumn::make('user.username')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('total_amount')
          ->money('VND')
          ->sortable(),
        Tables\Columns\SelectColumn::make('status')
          ->options([
            'pending' => 'Chờ xử lý',
            'processing' => 'Đang xử lý',
            'shipped' => 'Đã giao hàng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
          ]),
        Tables\Columns\TextColumn::make('phone')
          ->searchable(),
        Tables\Columns\TextColumn::make('maps_url')
          ->label('Bản đồ')
          ->formatStateUsing(fn() => 'Google Maps')
          ->url(fn(ShopOrder $record) => $record->maps_url, shouldOpenInNewTab: true)
          ->color('success')
          ->placeholder('—'),
        Tables\Columns\TextColumn::make('created_at')
          ->dateTime()
          ->sortable(),
      ])
      ->filters([
        //
      ])
      ->actions([
        Tables\Actions\ViewAction::make(),
        Tables\Actions\EditAction::make(),
      ])
      ->bulkActions([
        Tables\Actions\BulkActionGroup::make([
          Tables\Actions\DeleteBulkAction::make(),
        ]),
      ]);
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
      'index' => Pages\ListShopOrders::route('/'),
      'create' => Pages\CreateShopOrder::route('/create'),
      'view' => Pages\ViewShopOrder::route('/{record}'),
      'edit' => Pages\EditShopOrder::route('/{record}/edit'),
    ];
  }
}
