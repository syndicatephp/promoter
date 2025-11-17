<?php

namespace Syndicate\Promoter\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\IconPosition;
use Syndicate\Promoter\Enums\RobotsDirective;

class EditSeo extends EditRecord
{
    protected static bool $isLazy = false;
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'SEO';
    protected static ?string $breadcrumb = 'SEO';
    protected static ?string $title = 'Edit SEO';

    public function form(Form $form): Form
    {
        return $form->schema([
            Grid::make()
                ->relationship('seoData')
                ->schema([
                    Section::make('Keyword & Insights')
                        ->aside()
                        ->columns(2)
                        ->description('Set a target keyword used for optimization. The AI also derives a generated keyword from content and provides a relevance score.')
                        ->schema([
                            TextInput::make('keyword')
                                ->label('Target Keyword')
                                ->helperText('User-provided keyword that determines the optimization of title, description, image alt, and slug when set.')
                                ->maxLength(255),
                            TextInput::make('generated_keyword')
                                ->label('Generated Keyword')
                                ->helperText('AI-generated from content; always present but only used for optimization when no target keyword is provided.')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    Section::make('Title')
                        ->aside()
                        ->description('Essential for search engine visibility. Appears as the main clickable link in search results.')
                        ->schema([
                            TextInput::make('title')
                                ->helperText('Keep it concise and relevant. Aim for 20-60 characters.')
                                ->minLength(20)
                                ->maxLength(60)
                                ->placeholder($this->getRecord()->seo->getTitle())
                        ]),
                    Section::make('Description')
                        ->aside()
                        ->description('Appears below the title in search results. A compelling description can improve click-through rate.')
                        ->schema([
                            Textarea::make('description')
                                ->helperText('Summarizes the page content. Aim for 50-160 characters.')
                                ->minLength(50)
                                ->maxLength(160)
                                ->autosize()
                                ->placeholder($this->getRecord()->seo->getDescription())
                        ]),
                    Section::make('Search Engine Indexing')
                        ->aside()
                        ->description('Control how search engine crawlers index this page and follow links from it. Choose the appropriate directive based on your content strategy.')
                        ->schema([
                            Select::make('robots')
                                ->label('Robots Directive')
                                ->options(RobotsDirective::class)
                                ->placeholder($this->getRecord()->seo->getRobots())
                                ->helperText('Select the appropriate rule for search engine bots.')
                        ]),
                    Section::make('Canonical URL')
                        ->aside()
                        ->description('Specify the preferred ("canonical") URL for this content, especially if it exists on multiple URLs or has variations (e.g., with/without www, HTTP/HTTPS), to prevent duplicate content issues.')
                        ->schema([
                            TextInput::make('canonical_url')
                                ->label('Canonical URL')
                                ->helperText('Leave empty to use the page\'s default URL. Ensures search engines know the primary source.')
                                ->url()
                                ->placeholder($this->getRecord()->seo->getCanonicalUrl())
                        ])
                ])
                ->columns(1)
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->action(function ($livewire): void {
                    $livewire->save();
                })
                ->tooltip('Save current Record')
                ->submit(null)
                ->label('Save')
                ->color('success')
                ->icon('heroicon-o-bookmark-square')
                ->iconPosition(IconPosition::After)
                ->keyBindings(['mod+s']),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
