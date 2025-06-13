<?php

namespace Tapp\FilamentValueRangeFilter\Filters;

use Closure;
use Filament\Schemas;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Tapp\FilamentValueRangeFilter\Concerns\HasCurrency;

class ValueRangeFilter extends Filter
{
    use HasCurrency;

    protected ?string $indicatorBetweenLabel = null;

    protected ?string $indicatorEqualLabel = null;

    protected ?string $indicatorGreaterThanLabel = null;

    protected ?string $indicatorLessThanLabel = null;

    protected string|Closure $locale = 'en';

    protected RangeFilterCond|Closure|null $defaultCondition = null;

    protected bool|Closure $rangeOnly = false;

    protected function setUp(): void
    {
        parent::setup();

        $this
            ->form(fn() => [
                Schemas\Components\Fieldset::make($this->getlabel())
                    ->default(function () {
                        if ($this->isRangeOnly()) {
                            return [];
                        }

                        return ['range_condition' => $this->getDefaultCondition()?->value];
                    })
                    ->schema([
                        Forms\Components\Select::make('range_condition')
                            ->hiddenLabel()
                            ->placeholder('Select condition')
                            ->live()
                            ->options([
                                RangeFilterCond::Equal->value => __('filament-value-range-filter::filament-value-range-filter.range.options.equal'),
                                RangeFilterCond::Between->value => __('filament-value-range-filter::filament-value-range-filter.range.options.between'),
                                RangeFilterCond::GreaterThan->value => __('filament-value-range-filter::filament-value-range-filter.range.options.greater_than'),
                                RangeFilterCond::LessThan->value => __('filament-value-range-filter::filament-value-range-filter.range.options.less_than'),
                            ])
                            ->afterStateUpdated(function (Set $set) {
                                $set('range_equal', null);
                                $set('range_between_from', null);
                                $set('range_between_to', null);
                                $set('range_greater_than', null);
                                $set('range_less_than', null);
                            })
                            ->visible(fn() => !$this->isRangeOnly()),
                        Forms\Components\TextInput::make('range_equal')
                            ->hiddenLabel()
                            ->numeric()
                            ->placeholder(fn(): string => $this->getFormattedValue(0))
                            ->visible(fn(Get $get
                            ): bool => $get('range_condition') === !$this->isRangeOnly() && (RangeFilterCond::Equal->value || empty($get('range_condition')))),
                        Forms\Components\Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Forms\Components\TextInput::make('range_between_from')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->placeholder(fn(): string => $this->getFormattedValue(0)),
                                Forms\Components\TextInput::make('range_between_to')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->placeholder(fn(): string => $this->getFormattedValue(0)),
                            ])
                            ->visible(fn(Get $get
                            ): bool => $this->isRangeOnly() || $get('range_condition') === RangeFilterCond::Between->value),
                        Forms\Components\TextInput::make('range_greater_than')
                            ->hiddenLabel()
                            ->numeric()
                            ->placeholder(fn(): string => $this->getFormattedValue(0))
                            ->visible(fn(Get $get
                            ): bool => $get('range_condition') === RangeFilterCond::GreaterThan->value),
                        Forms\Components\TextInput::make('range_less_than')
                            ->hiddenLabel()
                            ->numeric()
                            ->placeholder(fn(): string => $this->getFormattedValue(0))
                            ->visible(fn(Get $get
                            ): bool => $get('range_condition') === RangeFilterCond::LessThan->value),
                    ])
                    ->columns(1),
            ])
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['range_between_from'] || $data['range_between_to']) {
                    $indicators[] = Indicator::make($this->getIndicatorBetweenLabel() ?? $this->getLabel() . ' is between ' . $this->getFormattedValue($data['range_between_from']) . ' and ' . $this->getFormattedValue($data['range_between_to']))
                        ->removeField('range_between_from')
                        ->removeField('range_between_to');
                }

                if ($data['range_equal']) {
                    $indicators[] = Indicator::make($this->getIndicatorEqualLabel() ?? $this->getLabel() . ' is equal to ' . $this->getFormattedValue($data['range_equal']))
                        ->removeField('range_equal');
                }

                if ($data['range_greater_than']) {
                    $indicators[] = Indicator::make($this->getIndicatorGreaterThanLabel() ?? $this->getLabel() . ' is greater than ' . $this->getFormattedValue($data['range_greater_than']))
                        ->removeField('range_greater_than');
                }

                if ($data['range_less_than']) {
                    $indicators[] = Indicator::make($this->getIndicatorLessThanLabel() ?? $this->getLabel() . ' is less than ' . $this->getFormattedValue($data['range_less_than']))
                        ->removeField('range_less_than');
                }

                return $indicators;
            })
            ->resetState(function () {
                $filterFields = $this->getForm()?->getFlatFields() ?? [];
                foreach ($filterFields as $filterField) {
                    $filterField->state(null);
                }

                return [
                    'range_equal' => null,
                    'range_between_from' => null,
                    'range_between_to' => null,
                    'range_greater_than' => null,
                    'range_less_than' => null,
                ];
            });
    }

    protected function getValue($value)
    {
        if ($this->isCurrency()) {
            return $this->isCurrencyInSmallestUnit ? $value * 100 : $value;
        }

        return $value;
    }

    public function apply(Builder $query, array $data = []): Builder
    {
        if ($this->hasQueryModificationCallback()) {
            return parent::apply($query, $data);
        }

        return $query
            ->when(
                $data['range_equal'],
                fn(Builder $query, $value): Builder => $query->where($this->getName(), '=',
                    $this->getValue($value)),
            )
            ->when(
                $data['range_between_from'],
                function (Builder $query, $value) use ($data) {
                    $query->where($this->getName(), '>=', $this->getValue($data['range_between_from']));
                },
            )
            ->when(
                $data['range_between_to'],
                function (Builder $query, $value) use ($data) {
                    $query->where($this->getName(), '<=', $this->getValue($data['range_between_to']));
                },
            )
            ->when(
                $data['range_greater_than'],
                fn(Builder $query, $value): Builder => $query->where($this->getName(), '>',
                    $this->getValue($value)),
            )
            ->when(
                $data['range_less_than'],
                fn(Builder $query, $value): Builder => $query->where($this->getName(), '<',
                    $this->getValue($value)),
            );
    }

    protected function getFormattedValue($value)
    {
        if ($this->isCurrency() && $value !== null) {
            return $this->isCurrency ? Number::currency($value, in: $this->currencyCode,
                locale: $this->locale) : $value;
        }

        return $value;
    }

    public function indicatorBetweenLabel(?string $label): static
    {
        $this->indicatorBetweenLabel = $label;

        return $this;
    }

    public function getIndicatorBetweenLabel(): ?string
    {
        return $this->evaluate($this->indicatorBetweenLabel);
    }

    public function indicatorEqualLabel(?string $label): static
    {
        $this->indicatorEqualLabel = $label;

        return $this;
    }

    public function getIndicatorEqualLabel(): ?string
    {
        return $this->evaluate($this->indicatorEqualLabel);
    }

    public function indicatorGreaterThanLabel(?string $label): static
    {
        $this->indicatorGreaterThanLabel = $label;

        return $this;
    }

    public function getIndicatorGreaterThanLabel(): ?string
    {
        return $this->evaluate($this->indicatorGreaterThanLabel);
    }

    public function indicatorLessThanLabel(?string $label): static
    {
        $this->indicatorLessThanLabel = $label;

        return $this;
    }

    public function getIndicatorLessThanLabel(): ?string
    {
        return $this->evaluate($this->indicatorLessThanLabel);
    }

    public function locale(string|Closure $locale = 'en'): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->evaluate($this->locale);
    }

    public function setDefaultCondition(RangeFilterCond|Closure|null $cond): static
    {
        $this->defaultCondition = $cond;

        return $this;
    }

    public function getDefaultCondition(): ?RangeFilterCond
    {
        if ($this->defaultCondition instanceof RangeFilterCond) {
            return $this->defaultCondition;
        }

        return $this->evaluate($this->defaultCondition);
    }

    public function setRangeOnly(Closure|bool $closure): static
    {
        $this->rangeOnly = $closure;

        return $this;
    }

    public function isRangeOnly(): bool
    {
        return $this->evaluate($this->rangeOnly);
    }
}
