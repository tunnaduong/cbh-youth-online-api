<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopProductResource\Pages;
use App\Filament\Resources\ShopProductResource\RelationManagers;
use App\Models\ShopProduct;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShopProductResource extends Resource
{
  protected static ?string $model = ShopProduct::class;

  protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Forms\Components\TextInput::make('name')
          ->required()
          ->live(onBlur: true)
          ->afterStateUpdated(fn(string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', str()->slug($state)) : null),
        Forms\Components\TextInput::make('slug')
          ->disabled()
          ->dehydrated()
          ->required()
          ->unique(ShopProduct::class, 'slug', ignoreRecord: true),
        Forms\Components\Select::make('category_id')
          ->relationship('category', 'name')
          ->required(),
        Forms\Components\TextInput::make('price')
          ->required()
          ->numeric()
          ->prefix('đ'),
        Forms\Components\TextInput::make('stock')
          ->required()
          ->numeric()
          ->default(0),
        Forms\Components\FileUpload::make('image_url')
          ->image()
          ->directory('shop-products'),
        Forms\Components\Toggle::make('is_active')
          ->required()
          ->default(true),
        Forms\Components\RichEditor::make('description')
          ->columnSpanFull(),
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        Tables\Columns\ImageColumn::make('image_url'),
        Tables\Columns\TextColumn::make('name')
          ->searchable(),
        Tables\Columns\TextColumn::make('category.name')
          ->sortable(),
        Tables\Columns\TextColumn::make('price')
          ->money('VND')
          ->sortable(),
        Tables\Columns\TextColumn::make('stock')
          ->numeric()
          ->sortable(),
        Tables\Columns\IconColumn::make('is_active')
          ->boolean(),
        Tables\Columns\TextColumn::make('created_at')
          ->dateTime()
          ->sortable()
          ->toggleable(isToggledHiddenByDefault: true),
      ])
      ->filters([
        //
      ])
      ->actions([
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
      'index' => Pages\ListShopProducts::route('/'),
      'create' => Pages\CreateShopProduct::route('/create'),
      'edit' => Pages\EditShopProduct::route('/{record}/edit'),
    ];
  }
}
