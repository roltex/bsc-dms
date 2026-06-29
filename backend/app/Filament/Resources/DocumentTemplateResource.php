<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use App\Models\PlaceholderVariable;
use App\Models\TemplateTable;
use App\Services\TemplateVariableRegistry;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $variableRefHtml = self::buildVariableReferenceHtml();

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Supply Agreement Template'),
                Select::make('document_category_id')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Category'),
                FileUpload::make('path')
                    ->label('Template File')
                    ->disk('local')
                    ->directory('templates')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/msword',
                    ])
                    ->maxSize(20480)
                    ->columnSpanFull()
                    ->helperText('Upload a Word document (.doc or .docx) with {{VARIABLE}} placeholders. Max 20MB.'),
                TagsInput::make('editable_sections')
                    ->label('Editable Sections')
                    ->placeholder('Add a section name...')
                    ->helperText('Define sections users can edit (e.g. "Party Details", "Payment Terms").')
                    ->columnSpanFull(),
                Placeholder::make('detected_variables_display')
                    ->label('Detected Variables (from document)')
                    ->content(function (?DocumentTemplate $record): HtmlString {
                        if (! $record || empty($record->detected_variables)) {
                            return new HtmlString('<span style="color:#94a3b8;font-size:13px">Save the template first to detect variables.</span>');
                        }
                        $knownKeys = TemplateVariableRegistry::allKeys();
                        $badges = [];
                        foreach ($record->detected_variables as $var) {
                            $isKnown = in_array($var, $knownKeys, true);
                            $color = $isKnown ? '#16a34a' : '#ea580c';
                            $title = $isKnown ? 'Known system variable' : 'Unrecognized variable';
                            $badges[] = "<span title=\"{$title}\" style=\"display:inline-block;padding:2px 8px;margin:2px;border-radius:4px;font-size:12px;font-weight:500;background:{$color}20;color:{$color};border:1px solid {$color}40\">{{".$var."}}</span>";
                        }

                        return new HtmlString(implode(' ', $badges));
                    })
                    ->columnSpanFull()
                    ->visibleOn('edit'),
                Select::make('extra_variables')
                    ->label('Additional Placeholders for Contract Details')
                    ->multiple()
                    ->searchable()
                    ->options(function () {
                        return PlaceholderVariable::where('is_active', true)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($pv) => [$pv->key => "{{$pv->key}} — {$pv->label}"])
                            ->toArray();
                    })
                    ->columnSpanFull()
                    ->helperText('Add placeholders that should appear in the frontend Contract Details form, even if they are not in the document. Detected placeholders from the document always appear automatically.'),
                Placeholder::make('available_variables_ref')
                    ->label('Available Template Variables')
                    ->content(new HtmlString($variableRefHtml))
                    ->columnSpanFull(),

                Section::make('Template Tables')
                    ->description('Define data tables that can be filled with inventory items during task creation. Use {{TABLE:SHORTCODE}} in Google Docs to insert the table.')
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('template_tables_data')
                            ->label('')
                            ->addActionLabel('Add Table')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'New Table')
                            ->afterStateHydrated(function ($component, $state, ?DocumentTemplate $record) {
                                if (! $record) {
                                    return;
                                }
                                $tables = $record->templateTables()->orderBy('sort_order')->get();
                                $data = $tables->map(fn ($t) => [
                                    'id' => $t->id,
                                    'name' => $t->name,
                                    'shortcode' => $t->shortcode,
                                    'columns' => $t->columns ?? [],
                                ])->toArray();
                                $component->state($data);
                            })
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->placeholder('e.g. Items List'),
                                TextInput::make('shortcode')
                                    ->required()
                                    ->placeholder('e.g. ITEMS')
                                    ->helperText('Use UPPER_SNAKE_CASE. Insert {{TABLE:SHORTCODE}} in your document.')
                                    ->rules(['regex:/^[A-Z][A-Z0-9_]*$/']),
                                Repeater::make('columns')
                                    ->label('Columns')
                                    ->addActionLabel('Add Column')
                                    ->schema([
                                        TextInput::make('key')
                                            ->required()
                                            ->placeholder('e.g. item_name'),
                                        TextInput::make('label')
                                            ->required()
                                            ->placeholder('e.g. Item Name'),
                                        Select::make('source')
                                            ->required()
                                            ->options(array_merge(
                                                ['custom' => '— Custom (manual input) —'],
                                                collect(TemplateTable::INVENTORY_FIELDS)
                                                    ->mapWithKeys(fn ($label, $field) => ["inventory:{$field}" => "Inventory: {$label}"])
                                                    ->toArray()
                                            ))
                                            ->searchable()
                                            ->default('custom'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0),
                            ])
                            ->defaultItems(0)
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Category')->sortable(),
                TextColumn::make('detected_variables')
                    ->label('Variables')
                    ->formatStateUsing(function ($state) {
                        if (! is_array($state) || empty($state)) {
                            return '—';
                        }

                        return count($state).' vars';
                    })
                    ->badge(),
                TextColumn::make('editable_sections')
                    ->label('Sections')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state).' sections' : '—')
                    ->badge(),
                TextColumn::make('template_tables_count')
                    ->label('Tables')
                    ->counts('templateTables')
                    ->badge(),
                TextColumn::make('path')
                    ->label('File')
                    ->formatStateUsing(fn ($state) => $state ? basename($state) : 'No file')
                    ->limit(30),
                TextColumn::make('created_at')->date('d M Y')->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('document_category_id')
                    ->relationship('category', 'name')
                    ->label('Category')
                    ->preload(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }

    private static function buildVariableReferenceHtml(): string
    {
        $groups = TemplateVariableRegistry::all();
        $html = '<div style="font-size:13px;line-height:1.8">';
        foreach ($groups as $group) {
            $html .= '<strong style="display:block;margin-top:8px">'.$group['label'].'</strong>';
            foreach ($group['variables'] as $var) {
                $html .= '<code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;font-size:12px">{{'.$var['key'].'}}</code> — '.$var['label'].'<br>';
            }
        }
        $html .= '</div>';

        return $html;
    }
}
