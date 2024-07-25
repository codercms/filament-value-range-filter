<?php

namespace Tapp\FilamentValueRangeFilter\Filters;

enum RangeFilterCond: string
{
    case Equal = 'equal';
    case Between = 'between';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
}
