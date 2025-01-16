<?php

/*******************************************************************************
 * Game: RT7sLuck_rtp96
 * Version: 4.0.1
 * Hash: 5403a6bd57a5d8f3f6e912992d14f940
 *
 * Classes: 
 *   Math\Games\GameException
 *   Math\Games\RNGInterface
 *   Math\Games\Slot\Lines\LineStrategyInterface
 *   Math\Games\Slot\Lines\Strategy\LineType
 *   Math\Games\Slot\Lines\Strategy\AW
 *   Math\Games\Slot\Lines\Strategy\LTR
 *   Math\Games\Slot\Lines\Strategy\RTL
 *   Math\Games\Slot\Lines\Strategy\BW
 *   Math\Games\Utils\Decimal
 *   Math\Games\Slot\Lines\Line
 *   Math\Games\Utils\Calculator
 *   Math\Games\Slot\Lines\Strategy\Cluster
 *   Math\Games\Slot\Lines\Strategy\ScatterWays
 *   Math\Games\Slot\Lines\Strategy\SuperLines
 *   Math\Games\Slot\Lines\Strategy\Ways
 *   Math\Games\Slot\Lines\Strategy\WaysAW
 *   Math\Games\Slot\Lines\Strategy\ScatterPays
 *   Math\Games\Slot\Tile
 *   Math\Games\Slot\Lines\LineCalculator
 *   Math\Games\Utils\Chance
 *   Math\Games\Slot\ActionInterface
 *   Math\Games\Slot\ActionFormatInterface
 *   Math\Games\Slot\Helpers\BufferHelper
 *   Math\Games\Slot\Buffer
 *   Math\Games\Slot\Trigger
 *   Math\Games\Slot\ResultInterface
 *   Math\Games\Slot\Result
 *   Math\Games\Slot\Action
 *   Math\Games\Slot\Actions\FeatureChance
 *   Math\Games\Slot\Actions\Strategies\RandomPositionsStrategyInterface
 *   Math\Games\Slot\Actions\RandomPositions
 *   Math\Games\Slot\Actions\RandomTiles
 *   Math\Games\Slot\Actions\Strategies\RandomPositionsStrategy
 *   Math\Games\Slot\Actions\Strategies\ReelPositionsStrategy
 *   Math\Games\Slot\FeatureBuyInterface
 *   Math\Games\Slot\RT7sLuck\Format\FatTileFormat
 *   Math\Games\Slot\Helpers\ResultHelper
 *   Math\Games\Slot\Actions\FreeSpins
 *   Math\Games\Slot\Helpers\SlotHelper
 *   Math\Games\Utils\Rules
 *   Math\Games\Slot\SlotInterface
 *   Math\Games\Slot\SlotGame
 *   Math\Games\Slot\FreeSpinGame
 *   Math\Games\Slot\RT7sLuck\Format\LockedTilesFormat
 *   Math\Games\Slot\RT7sLuck\Format\ScattersFormat
 *   Math\Games\Slot\RT7sLuck\RT7sLuck
 *   Math\Games\Slot\RT7sLuck\RT7sLuck_rtp96
 ******************************************************************************/

namespace math\games\slot\games_r4\RT7sLuck;



/*******************************************************************************
 * Class: Math\Games\GameException
 ******************************************************************************/

class GameException extends \Exception {
    public function __construct($message = '', $code = 0) {
        parent::__construct($message, $code);
    }
}


/*******************************************************************************
 * Class: Math\Games\RNGInterface
 ******************************************************************************/

interface RNGInterface {
    public static function getInstance();
    public function random($min = 0, $max = PHP_INT_MAX);
    public function seed($seed = null);
    public function getSeed();
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\LineStrategyInterface
 ******************************************************************************/

interface LineStrategyInterface
{
    public function calculate(array $screen, array $wilds, array $multipliers): array;
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\LineType
 ******************************************************************************/

abstract class LineType
{
    const LTR         = 'LTR';
    const RTL         = 'RTL';
    const BW          = 'BW';
    const AW          = 'AW';
    const WAYS        = 'WAYS';
    const WAYS_AW     = 'AW_WAYS';
    const CLUSTER     = 'CLUSTER';
    const SUPER_LINES = 'SUPER_LINES';
    const SCATTER_PAYS= 'SCATTER_PAYS';
    const SCATTER_WAYS = 'SCATTER_WAYS';
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\AW
 ******************************************************************************/

class AW implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $lines = [];
        foreach ($this->definitions as $id => $definition) {
            foreach ($this->find($definition, $screen, $wilds, $this->payouts) as $line) {
                $line['id'] = $id;
                $lines[] = $line;
            }
        }
        return $lines;
    }
    private function find(array $definition, array $screen, array $wilds, array $payouts): array
    {
        $hasWilds = count($wilds) > 0;
        $wildIds = $hasWilds ? array_keys($wilds) : [];
        $tiles = [];
        foreach ($definition as $x => $y) {
            if (!isset ($screen[$x][$y])) {
                throw new GameException("The screen is not in the right format. \$screen[$x][$y] was not found.");
            }
            $tiles[] = (int) $screen[$x][$y];
        }
        $wildGroups = [];
        $wildMultipliers = [];
        if ($hasWilds) {
            $tmp = $this->findStreaks($wildIds, $tiles);
            foreach ($tmp as $streak) {
                $left = $tiles[$streak['start'] - 1] ?? null;
                $right = $tiles[$streak['end'] + 1] ?? null;
                if (isset($wilds[$left])) $left = null;
                if (isset($wilds[$right])) $right = null;
                if ($left === $right) $right = null;
                $streak['replacements'] = [];
                if ($left !== null) $streak['replacements'][] = $left;
                if ($right !== null) $streak['replacements'][] = $right;
                $wildSlice = array_slice($tiles, $streak['start'], $streak['count']);
                $wildSliceCounts = array_count_values($wildSlice);
                if (1 < count($wildSliceCounts)) {
                    $streak['replacements'] = array_merge($streak['replacements'], array_keys($wildSliceCounts));
                }
                $wildGroups[] = $streak;
            }
        }
        if ($hasWilds) foreach ($tiles as $x => $tileId) {
            if (!isset ($wilds[$tileId])) continue;
            $wildMultipliers[$x] = $wilds[$tileId];
        }
        $variations = [$tiles];
        $replacements = [];
        foreach ($wildGroups as $g => $group) {
            if ($hasWilds && empty($group['replacements'])){
                $replacements[] = array_values(array_unique($tiles));
                continue;
            }
            $replacements[] = $group['replacements'];
        }
        $wildCombos = $this->getCombinations($replacements);
        foreach ($wildCombos as $c => $combo) {
            if(empty($combo)) continue;
            $variation = $tiles;
            foreach ($combo as $g => $tileId) {
                for ($x = $wildGroups[$g]['start']; $x <= $wildGroups[$g]['end']; $x++) $variation[$x] = $tileId;
            }
            $variations[] = $variation;
        }
        $lines = [];
        foreach ($variations as $i => $tiles) {
            $lastTileId = null;
            $streak = 0;
            $start = 0;
            $isLastLoop = false;
            for ($j = 0, $iMax = count($tiles); $j < $iMax; $j++) {
                $tileId = current($tiles);
                if(next($tiles) === false) $isLastLoop = true;
                if ($lastTileId === null) $lastTileId = $tileId;
                if ($lastTileId === $tileId ) {
                    $streak++;
                    if(!$isLastLoop) continue; 
                    $j = $iMax;
                }
                $weight = $payouts[$lastTileId][$streak - 1];
                $multiplier = $this->getLineMultiplier($start, $streak, $wildMultipliers);
                if($weight * $multiplier > 0) {
                    $lines[] = [
                        'tileId'     => $lastTileId,
                        'start'      => $start,
                        'length'     => $streak,
                        'positions'  => $this->getBestSlots($definition, $start, $j),
                        'payout'     => $weight,
                        'definition' => $definition,
                        'multiplier' => $multiplier,
                        'ways'       => 1,
                        'type'       => LineType::AW
                    ];
                }
                $start = $j;
                $streak = 1;
                $lastTileId = $tileId;
            }
        }
        if (!$lines) {
            return [];
        }
        $variations = $this->createVariations($lines, $definition);
        $index  = $this->getBiggestLinesFromVariation($variations);
        usort($variations[$index], static function ($a, $b) { return $a['start'] > $b['start'];});
        return $variations[$index];
    }
    private function createVariations(array $lines, array $definition): array
    {
        $comb = [];
        $definitionMatrix = array_fill(0, count($definition), 0);
        for ($i = 0, $iMax = count($lines); $i < $iMax; $i++) {
            $matrix = $definitionMatrix;
            $lineA = $lines[$i];
            $group = [];
            $group[$lineA['start']] = $lineA;
            foreach ($lineA['positions'] as $x => $v) $matrix[$x] = $v;
            foreach ($lines as $j => $lineB) {
                if (($lineA !== $lineB) && false === $this->lineIntersect($lineB, $matrix)) {
                    $group[$lineB['start']] = $lineB;
                    foreach ($lineB['positions'] as $x => $v) {
                        if ($v === 1) $matrix[$x] = $v;
                    }
                }
            }
            $comb[$i] = array_values($group);
        }
        return $comb;
    }
    protected function lineIntersect(array &$line, array &$matrix): bool
    {
        foreach ($line['positions'] as $x => $v) {
            if ($v === 1 && $matrix[$x] === 1) {
                return true;
            }
        }
        return false;
    }
    protected function getBiggestLinesFromVariation(array $variations) {
        $groups = [];
        foreach ($variations as $key => $variation) {
            $groups[$key] = [
                'weightSum'  => 0,
                'streakMax'  => 0,
                'linesCount' => 0,
                'tileIdMax'  => 0,
            ];
            foreach ($variation as $line) {
                $groups[$key]['weightSum'] += $line['payout'];
                $groups[$key]['linesCount'] ++;
                if($line['length'] > $groups[$key]['streakMax']) {
                    $groups[$key]['streakMax'] = $line['length'];
                    $groups[$key]['tileIdMax'] = $line['tileId'];
                }
            }
        }
        uasort($groups, [$this, 'sortGroups']);
        end($groups); 
        return key($groups);
    }
    protected function getCombinations(array &$arrays, $p = 0, array $current = [], array &$result = null): array
    {
        if (!$result || !is_array($result)) $result = [];
        if (count($arrays) <= $p) {
            $result[] = $current;
            return $result;
        }
        foreach ($arrays[$p] as $i => $n) {
            $current[] = $n;
            $this->getCombinations($arrays, $p + 1, $current, $result);
            array_pop($current);
        }
        return $result;
    }
    protected function getLineMultiplier($start, $streak, $wildMultipliers): int
    {
        $multiplier = 1;
        for ($ix = 0; $ix < $streak; $ix++) {
            $wildPosition = $ix + $start;
            if (isset($wildMultipliers[$wildPosition]) && $wildMultipliers[$wildPosition] > $multiplier) {
                $multiplier = $wildMultipliers[$wildPosition];
            }
        }
        return $multiplier;
    }
    protected function findStreaks(&$wildIds, &$lineIds): array
    {
        $count = 0;
        $start = null;
        $streaks = [];
        $max = count($lineIds) - 1;
        $makeStreak = static function (int $start, int $count) {
            return [
                'count' => $count,
                'start' => $start,
                'end' => $start + $count - 1
            ];
        };
        foreach ($lineIds as $i => $tileId) {
            if (in_array($tileId, $wildIds, true)) {
                if ($start === null) $start = $i;
                $count++;
                if ($i >= $max) $streaks[] = $makeStreak($start, $count);
            } else {
                if ($count > 0) $streaks[] = $makeStreak($start, $count);
                $count = 0;
                $start = null;
            }
        }
        return $streaks;
    }
    private function getBestSlots(array $lineDefinition, $startIndex, $stopIndex): array
    {
        $bestSlots = [];
        foreach ($lineDefinition as $i => $slot) {
            if ($i >= $startIndex && $i < $stopIndex) $bestSlots[$i] = 1;
            else $bestSlots[$i] = 0;
        }
        return $bestSlots;
    }
    public static function sortGroups($groupA, $groupB) {
        if($groupA['weightSum'] === $groupB['weightSum'] && $groupA['streakMax'] > $groupB['streakMax'] && ($groupA['linesCount'] > $groupB['linesCount'] || $groupA['tileIdMax'] > $groupB['tileIdMax'])) {
            return true;
        }
        if($groupA['weightSum'] === $groupB['weightSum'] && ($groupA['streakMax'] === $groupB['streakMax']  || $groupA['linesCount'] === $groupB['linesCount'])) {
            return $groupA['tileIdMax'] > $groupB['tileIdMax'];
        }
        if($groupA['weightSum'] === $groupB['weightSum'] && $groupA['streakMax'] === $groupB['streakMax']) {
            return $groupA['linesCount'] < $groupB['linesCount'];
        }
        if($groupA['weightSum'] === $groupB['weightSum'] && $groupA['linesCount'] === $groupB['linesCount']) {
            return $groupA['streakMax'] > $groupB['streakMax'];
        }
        return $groupA['weightSum'] > $groupB['weightSum'];
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\LTR
 ******************************************************************************/

class LTR implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $lines = [];
        foreach ($this->definitions as $id => $definition) {
            if ($line = $this->find($definition, $screen, $wilds, $this->payouts)) {
                $line['id'] = $id;
                $lines[] = $line;
            }
        }
        return $lines;
    }
    private function find(array $definition, array $screen, array $wilds, array $payouts): array {
        $streak = 0;
        $wildStreak = 0;
        $prev = null;
        $allWild = true;
        $highestMultiplier = 0;
        $multiplier = 1;
        $item = null;
        $wildItem = null;
        $current = null;
        $wasWild = null;
        foreach ($definition as $x => $y) {
            $current = (int) $screen[$x][$y];
            $isWild = isset ($wilds[$current]);
            if ($prev === null && !$isWild) $prev = $current;
            if ($allWild || $isWild || $current === $prev) {
                $streak ++;
                if ($allWild && $isWild && ($wasWild || $wasWild === null)) {
                    $wildStreak ++;
                    if ($wildItem && $payouts[$current][$wildStreak-1] > $payouts[$wildItem][$wildStreak-1]) {
                        $wildItem = $current;
                    }
                }
                if (!$isWild) {
                    $allWild = false;
                    $prev = $current;
                } elseif ($wilds[$current] > $highestMultiplier) {
                    $highestMultiplier = $wilds[$current];
                    if ($wildItem === null && isset($payouts[$current])) $wildItem = $current;
                }
                if ($isWild) $multiplier *= $wilds[$current];
            } else {
                $item = $prev;
                break;
            }
            $wasWild = $isWild;
        }
        if ($item === null && $prev !== null) $item = $prev;
        if ($wildItem === null && $current !== null && $allWild) $wildItem = $current;
        $weight = ($item !== null && isset ($payouts[$item]) && isset ($payouts[$item][$streak - 1])) ? $payouts[$item][$streak - 1] : 0;
        $wildWeight = ($wildItem !== null && isset ($payouts[$wildItem]) && isset ($payouts[$wildItem][$wildStreak - 1])) ? $payouts[$wildItem][$wildStreak - 1] : 0;
        if (!$weight && !$wildWeight) return [];
        $slots = [];
        $end = $wildWeight * $multiplier > $weight * $multiplier ? $wildStreak : $streak;
        foreach ($definition as $i => $slot) {
            $slots[$i] = $i < $end ? 1 : 0;
        }
        if ($wildWeight * $multiplier > $weight * $multiplier) {
            $item = $wildItem;
            $streak = $wildStreak;
            $weight = $wildWeight;
        }
        return [
            'tileId'     => $item,
            'start'      => 0,
            'length'     => $streak,
            'positions'  => $slots,
            'payout'     => $weight,
            'definition' => $definition,
            'multiplier' => $multiplier,
            'ways'       => 1,
            'type'       => LineType::LTR
        ];
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\RTL
 ******************************************************************************/

class RTL implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $reverseDefinitions = $this->definitions;
        foreach ($reverseDefinitions as $id => $definition) $reverseDefinitions[$id] = array_reverse($definition);
        $linesLTR = new LTR($reverseDefinitions, $this->payouts);
        $lines = $linesLTR->calculate(array_reverse($screen), $wilds, $multipliers);
        foreach ($lines as $k => $line) {
            $line['type'] = LineType::RTL;
            $lines[$k]['positions'] = array_reverse($line['positions']);
            $lines[$k]['start'] = count($line['positions']) - $line['length'];
        }
        return $lines;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\BW
 ******************************************************************************/

class BW implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $LTRCalculator = new LTR($this->definitions, $this->payouts);
        $RTLCalculator = new RTL($this->definitions, $this->payouts);
        $LTRLines = $LTRCalculator->calculate($screen, $wilds, $multipliers);
        $RTLLines = $RTLCalculator->calculate($screen, $wilds, $multipliers);
        $maxLength = count($screen);
        $linesIterator = new \MultipleIterator(\MultipleIterator::MIT_NEED_ANY | \MultipleIterator::MIT_KEYS_NUMERIC);
        $linesIterator->attachIterator(new \ArrayIterator($LTRLines));
        $linesIterator->attachIterator(new \ArrayIterator($RTLLines));
        $result = [];
        $tmpLines = [];
        foreach ($linesIterator as [$LTRLine, $RTLLine]) {
            if ($LTRLine) $tmpLines[$LTRLine['id']][] = $LTRLine;
            if ($RTLLine) $tmpLines[$RTLLine['id']][] = $RTLLine;
        }
        foreach ($tmpLines as $id => $lines) {
            $tileIds = array_unique(array_column($lines, 'tileId'));
            $lengths = array_unique(array_column($lines, 'length'));
            if (1 < count($lines)) {
                $bothIsWild =  isset($wilds[$lines[0]['tileId']], $wilds[$lines[1]['tileId']]);
                $sameTileId = $lines[0]['tileId'] === $lines[1]['tileId'];
                if (($sameTileId || $bothIsWild) && 1 === count($lengths) && $lengths[0] === $maxLength) {
                    usort($lines, static function ($l1, $l2) {
                        return $l1['tileId'] > $l2['tileId'];
                    });
                    array_pop($lines);
                }
            }
            $definition = $lines[0]['definition'];
            if (1 < count($lines) && count($definition) <= 5) {
                usort($lines, static function ($l1, $l2) {
                    return $l1['tileId'] < $l2['tileId'];
                });
                usort($lines, static function ($l1, $l2) {
                    return $l1['payout'] < $l2['payout'];
                });
                array_pop($lines);
            }
            foreach ($lines as $line) {
                $line['type'] = LineType::BW;
                $result[] = $line;
                if (count($tileIds) === 1 && count($lengths) === 1 && $lengths[0] === $maxLength) break;
            }
        }
        return $result;
    }
}


/*******************************************************************************
 * Class: Math\Games\Utils\Decimal
 ******************************************************************************/

class Decimal implements \JsonSerializable {
    public static $defaultScale = 2;
    private static function numberToString($value, $scale) {
        if (is_string($value)) $string = bcadd($value, '0', $scale);
        elseif ($value instanceof Decimal) $string = bcadd($value->toString(), '0', $scale);
        else {
            $formatted = number_format($value, $scale + 1, '.', '');
            $split = explode('.', $formatted);
            if (strlen($split[0]) > 8) {
                throw new \UnexpectedValueException('Number to string conversion does not work well with numbers bigger than 99 mil.');
            }
            $string = bcadd($formatted, '0', $scale);
        }
        return $string;
    }
    private $value;
    private $scale;
    public function __construct($value = null, $scale = null) {
        $this->scale = $scale !== null ? $scale : self::$defaultScale;
        $this->value = self::numberToString($value ?: '0', $this->scale);
    }
    public function add($value) {
        return new Decimal(bcadd($this->value, self::numberToString($value, $this->scale), $this->scale), $this->scale);
    }
    public function sub($value) {
        return new Decimal(bcsub($this->value, self::numberToString($value, $this->scale), $this->scale), $this->scale);
    }
    public function mul($value) {
        return new Decimal(bcmul($this->value, self::numberToString($value, $this->scale), $this->scale), $this->scale);
    }
    public function div($value) {
        return new Decimal(bcdiv($this->value, self::numberToString($value, $this->scale), $this->scale), $this->scale);
    }
    public function pow($value) {
        return new Decimal(bcpow($this->value, self::numberToString($value, 0), $this->scale), $this->scale);
    }
    public function sqrt() {
        return new Decimal(bcsqrt($this->value, $this->scale));
    }
    public function cmp($value) {
        return bccomp($this->value, self::numberToString($value, $this->scale), $this->scale);
    }
    public function isLarger($value) {
        return ($this->cmp($value) > 0);
    }
    public function isSmaller($value) {
        return ($this->cmp($value) < 0);
    }
    public function isEqual($value) {
        return ($this->cmp($value) === 0);
    }
    public function isLargerOrEqual($value) {
        return !$this->isSmaller($value);
    }
    public function isSmallerOrEqual($value) {
        return !$this->isLarger($value);
    }
    public function toFloat() {
        return (float) $this->value;
    }
    public function toString() {
        return $this->value;
    }
    public function __toString() {
        return $this->value;
    }
    public function jsonSerialize() {
        return $this->value;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Line
 ******************************************************************************/

class Line {
    private $id;
    private $tileId;
    private $start;
    private $length;
    private $slots;
    private $payout;
    private $multiplier;
    private $ways;
    private $lineType;
    private $definition;
    private $globalMultiplier;
    public function __construct($id, $tileId, $start, $length, $slots, $payout = 0, $definition = [], $multiplier = 1, $ways = 1, $lineType = LineType::LTR) {
        $this->id = $id;
        $this->tileId = $tileId;
        $this->start = $start;
        $this->length = $length;
        $this->slots = $slots;
        $this->payout = $payout;
        $this->multiplier = $multiplier;
        $this->ways = $ways;
        $this->lineType = $lineType;
        $this->definition = $definition;
        $this->globalMultiplier = 1;
    }
    public function calcWinnings() {
        return (new Decimal(1))->mul($this->payout)->mul($this->multiplier)->mul($this->ways);
    }
    public function getId() {
        return $this->id;
    }
    public function getTileId() {
        return $this->tileId;
    }
    public function getStart() {
        return $this->start;
    }
    public function getLength() {
        return $this->length;
    }
    public function getPayout() {
        return $this->payout;
    }
    public function getSlots() {
        return $this->slots;
    }
    public function getMultiplier() {
        return $this->multiplier;
    }
    public function getDefinition(): array
    {
        return $this->definition;
    }
    public function setMultiplier($value) {
        $this->multiplier = $value;
    }
    public function getWays() {
        return $this->ways;
    }
    public function setWays($value) {
        $this->ways = $value;
    }
    public function setLineType($value) {
        $this->lineType = $value;
    }
    public function getLineType() {
        return $this->lineType;
    }
    public function setGlobalMultiplier(int $globalMultiplier): void
    {
        $this->globalMultiplier = $globalMultiplier;
    }
    public function export (Decimal $stake) {
        $tmpLine = [
            'index' => $this->getId(),
            'start' => $this->getStart(),
            'length' => $this->getLength(),
            'tiles' => $this->getSlots(),
            'tile' => $this->getTileId(),
            'multiplier' => $this->getMultiplier(),
            'amount' => $this->calcWinnings()->mul($stake)->toString(),
            'multipliedAmount' => $this->calcWinnings()->mul($this->globalMultiplier)->mul($stake)->toString(),
            'type'=>$this->getLineType()
        ];
        if (LineType::WAYS === $this->lineType) $tmpLine['ways'] = $this->getWays();
        return $tmpLine;
    }
    public function getPositions(): array
    {
        $positions = [];
        switch ($this->lineType) {
            case LineType::LTR:
            case LineType::RTL:
            case LineType::AW:
            case LineType::BW:
            case LineType::SUPER_LINES:
                foreach ($this->definition as $x => $y) {
                    if (1 === $this->slots[$x]) {
                        $positions[$x.'-'.$y] = [$x, $y];
                    }
                }
                break;
            case LineType::CLUSTER:
            case LineType::WAYS:
            case LineType::SCATTER_PAYS:
            case LineType::WAYS_AW:
                foreach ($this->slots as $x => $reel) {
                    foreach ($reel as $y => $value) {
                        if (1 === $value) $positions[$x.'-'.$y] = [$x, $y];
                    }
                }
                break;
            default:
                throw new GameException(sprintf('Line::positions - type %s not implemented', $this->lineType));
        }
        return $positions;
    }
}


/*******************************************************************************
 * Class: Math\Games\Utils\Calculator
 ******************************************************************************/

class Calculator {
    public static function fitIn($num, $max) {
        $remainder = abs($num % $max);
        return ($num >= 0 || $remainder === 0) ? $remainder : $max - $remainder;
    }
    public static function getArrayBuffer(&$array, $offset, $length) {
        $count = $array instanceof \SplFixedArray ? $array->getSize() : count($array);
        $offset = self::fitIn($offset, $count);
        if ($count >= $offset + $length){
            $buffer = [];
            for ($i = 0; $i < $length; $i ++) {
                $buffer[] = $array[$offset + $i];
            }
        } else {
            $buffer1 = [];
            $buffer2 = [];
            for ($i = 0; $i < $count - $offset; $i ++) {
                $buffer1[] = $array[$offset + $i];
            }
            for ($i = 0; $i < ($offset + $length) - $count; $i ++) {
                $buffer2[] = $array[$i];
            }
            $buffer = array_merge($buffer1, $buffer2);
        }
        return $buffer;
    }
    public static function getStreaks(array $array, $item, $min = 2) {
        $count = 0;
        $start = null;
        $streaks = [];
        $length = count($array);
        foreach ($array as $i => $t) {
            if ($t === $item) {
                if ($start === null) $start = $i;
                $count ++;
                if ($i >= $length - 1 && $count >= $min) {
                    $streaks[] = [
                        'count' => $count,
                        'start' => $start,
                        'end' => $start + $count - 1
                    ];
                }
            } else {
                if ($count >= $min) {
                    $streaks[] = [
                        'count' => $count,
                        'start' => $start,
                        'end' => $start + $count - 1
                    ];
                }
                $count = 0;
                $start = null;
            }
            if ($count === 0 && $length - $i - 1 < $min) break;
        }
        return $streaks;
    }
    public static function getFigures(array $array, $item, $width, $height) {
        $streaks = [];
        $figures = [];
        $columns = 0;
        foreach ($array as $x => $column) {
            $streaks[$x] = self::getStreaks($column, $item, $height);
            $columns ++;
        }
        foreach ($streaks as $xStart => $column) {
            if ($xStart + $width > $columns) break;
            foreach ($column as $streak) {
                $yStart = null;
                $yCount = 0;
                for ($y = $streak['start']; $y <= $streak['end']; $y ++) {
                    $xCount = 1;
                    if ($array[$xStart][$y] === -1) continue;
                    for ($o = 1; $o < $width; $o ++) {
                        if (!isset ($array[$xStart + $o])) break;
                        if ($array[$xStart + $o][$y] === $item) $xCount ++;
                        else break;
                    }
                    if ($xCount >= $width) {
                        if ($yStart === null) $yStart = $y;
                        $yCount ++;
                        if ($yCount >= $height) {
                            $figures[] = [
                                'x' => $xStart,
                                'y' => $yStart
                            ];
                            for ($i = 0; $i < $width; $i ++) {
                                for ($j = 0; $j < $height; $j ++) {
                                    $array[$xStart + $i][$yStart + $j] = -1;
                                }
                            }
                            $yStart = null;
                            $yCount = 0;
                        }
                    } else {
                        $yStart = null;
                        $yCount = 0;
                    }
                    if ($yCount === 0 && $streak['end'] - $y < $height) break;
                }
            }
        }
        return $figures;
    }
    public static function getLongestTail(array &$array, $item, $x, $y, $width, $dir, $minWidth = 1) {
        $yNext = $y + $dir;
        if (!array_key_exists($yNext, $array[0])) {
            return ['x' => $x, 'y' => $y, 'width' => $width];
        }
        $row = [];
        for ($xNext = $x; $xNext < $x + $width; $xNext ++) {
            if (!isset($array[$xNext][$yNext])) break;
            $row[] = $array[$xNext][$yNext];
        }
        $streaks = self::getStreaks($row, $item, $minWidth);
        $longest = null;
        $maxOffset = 0;
        foreach ($streaks as $streak) {
            $higher = self::getLongestTail($array, $item, $streak['start'] + $x, $yNext, $streak['count'], $dir, $minWidth);
            $offset = abs($higher['y'] - $y);
            if ($offset > $maxOffset || !$longest) {
                $longest = $higher;
                $maxOffset = $offset;
            }
        }
        return $longest ?: ['x' => $x, 'y' => $y, 'width' => $width];
    }
    public static function getHighestFigure(array &$array, $item, $y, $dir, $minWidth = 1, $minHeight = 1) {
        $width = count($array);
        $row = [];
        for ($x = 0; $x < $width; $x ++) $row[] = $array[$x][$y];
        $streaks = self::getStreaks($row, $item, $minWidth);
        $highest = null;
        $maxOffset = 0;
        foreach ($streaks as $streak) {
            $higher = self::getLongestTail($array, $item, $streak['start'], $y, $streak['count'], $dir, $minWidth);
            $offset = abs($higher['y'] - $y);
            if ($offset + 1 < $minHeight) continue;
            if ($offset > $maxOffset || !$highest) {
                $highest = $higher;
                $maxOffset = $offset;
            }
        }
        if (!$highest) return null;
        return ['x' => $highest['x'], 'width' => $highest['width'], 'height' => $maxOffset + 1];
    }
    public static function getIncompleteFigures(array &$array, $item, $y, $dir, $width = 1, $minHeight = 1) {
        $arrayWidth = count($array);
        $row = [];
        for ($x = 0; $x < $arrayWidth; $x ++) $row[] = $array[$x][$y];
        $streaks = self::getStreaks($row, $item, $width);
        $figures = [];
        foreach ($streaks as $streak) {
            $incomplete = $streak['count'] / $width;
            for ($i = 0; $i < $incomplete; $i ++) {
                $longestTail = self::getLongestTail($array, $item, $streak['start'] + ($i * $width), $y,  $width, $dir, $width);
                $offset = abs($longestTail['y'] - $y);
                if ($offset + 1 < $minHeight) {
                    continue;
                }
                $figures[] = [
                    'x' => $longestTail['x'],
                    'width' => $longestTail['width'],
                    'height' => $offset+1
                ];
            }
        }
        if (!$figures) {
            return null;
        }
        return $figures;
    }
    public static function getStack(array &$array, array &$used, $x, $y, $value = null) {
        $current = $array[$x][$y];
        $key = $x . 'x' . $y;
        if (($value !== null && $current !== $value) || isset($used[$key])) return [];
        $result = [['x' => $x, 'y' => $y]];
        $used[$key] = true;
        if ($x + 1 < count($array)) {
            $result = array_merge($result, self::getStack($array, $used, $x + 1, $y, $current));
        }
        if ($y + 1 < count($array[$x])) {
            $result = array_merge($result, self::getStack($array, $used, $x, $y + 1, $current));
        }
        if ($x - 1 >= 0) {
            $result = array_merge($result, self::getStack($array, $used, $x - 1, $y, $current));
        }
        if ($y - 1 >= 0) {
            $result = array_merge($result, self::getStack($array, $used, $x, $y - 1, $current));
        }
        return $result;
    }
    public static function getStacks(array &$array, array $skipTiles = []) {
        $used = [];
        $stacks = [];
        foreach ($array as $rX => $reel) {
            foreach ($reel as $rY => $tile) {
                if (in_array($tile, $skipTiles, true)) $used[$rX.'x'.$rY] = true;
            }
        }
        $cx = count($array);
        for ($x = 0; $x < $cx; $x ++) {
            $cy = count($array[$x]);
            for ($y = 0; $y < $cy; $y ++) {
                $stack = self::getStack($array, $used, $x, $y);
                if (!empty ($stack)) {
                    $stacks[] = $stack;
                }
            }
        }
        return $stacks;
    }
    public static function measureStack(array &$stack) {
        $x = ['min' => null, 'max' => null];
        $y = ['min' => null, 'max' => null];
        $count = 0;
        foreach ($stack as $position) {
            $count ++;
            if ($x['min'] === null || $x['min'] > $position['x']) $x['min'] = $position['x'];
            if ($x['max'] === null || $x['max'] < $position['x']) $x['max'] = $position['x'];
            if ($y['min'] === null || $y['min'] > $position['y']) $y['min'] = $position['y'];
            if ($y['max'] === null || $y['max'] < $position['y']) $y['max'] = $position['y'];
        }
        $width = $x['max'] - $x['min'] + 1;
        $height = $y['max'] - $y['min'] + 1;
        return [
            'x' => $x['min'],
            'y' => $y['min'],
            'width' => $width,
            'height' => $height,
            'count' => $count
        ];
    }
    public static function cropStack(array &$stack, $x, $y, $width, $height) {
        $cropped = [];
        $mx = $x + $width - 1;
        $my = $y + $height - 1;
        foreach ($stack as $position) {
            if (
                $position['x'] >= $x && $position['x'] <= $mx &&
                $position['y'] >= $y && $position['y'] <= $my
            ) $cropped[] = $position;
        }
        return $cropped;
    }
    public static function get2dPositions(array $array, $items) {
        $found = [];
        $items = is_array($items) ? $items : [$items];
        foreach ($array as $x => $sub) {
            foreach ($sub as $y => $tmp) {
                if (in_array($tmp, $items, true)) $found[$x.'-'.$y] = [$x,$y,$tmp];
            }
        }
        return $found;
    }
    public static function countInArray(array $array, $item) {
        $count = 0;
        foreach ($array as $v) {
            if ($v === $item) $count++;
        }
        return $count;
    }
    public static function countIn2dArray(array $array, $item) {
        $count = 0;
        foreach ($array as $column) {
            foreach ($column as $v) {
                if ($v === $item) $count ++;
            }
        }
        return $count;
    }
    public static function getUpTo(array $array, $upTo, bool $isKeys=false, bool $preferValue=true)
    {
        $keys = $isKeys ? array_merge($array) : array_keys($array);
        sort($keys, SORT_NUMERIC);
        $key = null;
        foreach ($keys as $key) {
            if ($upTo <= $key) break;
            $key = null;
        }
        if ($preferValue) {
            return $array[$key] ?? null;
        }
        return $key;
    }
    public static function getUpToLower(array $array, $upTo, bool $isKeys = false, bool $preferValue=true) {
        $keys = $isKeys ? array_merge($array) : array_keys($array);
        sort($keys, SORT_NUMERIC);
        $key = null;
        $low = $min = current($keys);
        $max = end($keys);
        foreach ($keys as $key) {
            if ($upTo <= $key) break;
            $low = $key;
            $key = null;
        }
        if ($min > $upTo) {
            $key = null;
        } elseif ($upTo > $max) {
            $key = $max;
        } elseif ($key > $upTo) {
            $key = $low;
        }
        if ($preferValue && null !== $key) {
            return $array[$key];
        }
        return $key;
    }
    public static function distributeWeights($chances) {
        $sum = array_sum($chances);
        if (empty($chances) || (10000000 < (int)round($sum * 100000))) {
            throw new GameException('Distribution of weights cannot be more than 100.000% (' . (((int) round($sum * 100000)) / 100000) . ').');
        }
        asort($chances, SORT_NUMERIC);
        $sum = array_sum($chances);
        $tmp = 0;
        $largestKey = 0;
        foreach ($chances as $key => $value) {
            $chances[$key] = (int)round((float)(($value/$sum) * 10000), 2);
            if ($tmp < $chances[$key]) {
                $tmp = $value;
                $largestKey = $key;
            }
        }
        $sum = array_sum($chances);
        $remainder = 10000 - $sum;
        $chances[$largestKey] += $remainder;
        foreach ($chances as $key => $value) {
            $chances[$key] = (float)$chances[$key] / 100;
        }
        $sum = array_sum($chances);
        if (0.001 < abs($sum - 100)) {
            throw new GameException('Distribution of weights is not equal to 100%(' . $sum . ').');
        }
        return $chances;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\Cluster
 ******************************************************************************/

class Cluster implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $clusters = [];
        $cols = count($screen);
        $rows = count($screen[0]);
        $matrix = array_fill(0, $cols, array_fill(0, $rows, true));
        $defaultMask = array_fill(0, $cols, array_fill(0, $rows, 0));
        $wildPos = Calculator::get2dPositions($screen, array_keys($wilds));
        for ($x = 0, $xMax = count($screen);$x < $xMax; $x ++) {
            for ($y = 0, $yMax = count($screen[$x]);$y < $yMax; $y ++) {
                if (!$matrix[$x][$y] || !$screen[$x][$y]) continue;
                $matrix[$x][$y] = false;
                $result = $this->getGroups($screen, [$x,$y,$screen[$x][$y]], $matrix, $wilds);
                foreach ($wildPos as [$wX, $wY]) $matrix[$wX][$wY] = true;
                $tileId = $screen[$x][$y];
                $pays   = $this->payouts[$tileId];
                $max    = max(array_keys($pays));
                $count  = count($result);
                if ($max < $count) {
                    $payout = $pays[$max];
                } else {
                    $payout = $this->getUpTo($pays, $count, false, true);
                }
                if (0 < $payout) {
                    $currentMask = $defaultMask;
                    foreach ($result as $pos) {
                        $currentMask[$pos[0]][$pos[1]] = 1;
                    }
                    $clusters[] = [
                        'id' => 0,
                        'tileId' => $tileId,
                        'start' => 0,
                        'length' => $count,
                        'payout' => $payout,
                        'definition' => [],
                        'multiplier' => 1,
                        'positions' => $currentMask,
                        'ways' => 1,
                        'type' => LineType::CLUSTER
                    ];
                }
            }
        }
        return $clusters;
    }
    protected function getGroups(array &$screen, array $current, array &$matrix, array $wilds, array &$result = []): array {
        [$currentX, $currentY, $currentTile] = $current;
        $result[] = [$currentX, $currentY, $currentTile];
        $neighbors = $this->getNeighbors([$currentX, $currentY], $screen, $matrix);
        for ($i=0, $iMax = count($neighbors); $i < $iMax; $i ++) {
            [$nextX, $nextY] = $neighbors[$i];
            $nextTile   = $screen[$nextX][$nextY];
            $nextIsWild = isset($wilds[$nextTile]);
            if ($nextIsWild) $nextTile = $currentTile;
            $stepOnNext = $currentTile === $nextTile || $nextIsWild;
            if ($stepOnNext && $matrix[$nextX][$nextY]) {
                $matrix[$nextX][$nextY] = false;
                $this->getGroups($screen, [$nextX, $nextY, $currentTile], $matrix, $wilds, $result);
            }
        }
        return $result;
    }
    protected function getNeighbors(array $current, array &$screen, array &$matrix): array {
        $neighbors = [];
        [$x, $y] = $current;
        if (isset($screen[$x + 1][$y]) && $matrix[$x + 1][$y]) {
            $neighbors[] = [$x + 1, $y];
        }
        if (isset($screen[$x - 1][$y]) && $matrix[$x - 1][$y]) {
            $neighbors[] = [$x - 1, $y];
        }
        if (isset($screen[$x][$y - 1]) && $matrix[$x][$y - 1]) {
            $neighbors[] = [$x, $y - 1];
        }
        if (isset($screen[$x][$y + 1]) && $matrix[$x][$y + 1]) {
            $neighbors[] = [$x, $y + 1];
        }
        if (count($neighbors) <= 0) return [];
        return $neighbors;
    }
    protected function getUpTo(array $array, $upTo, $isKeys = false, $preferLower=false) {
        $keys = $isKeys ? array_merge($array) : array_keys($array);
        sort($keys, SORT_NUMERIC);
        $key = null;
        $low = null;
        foreach ($keys as $key) {
            if ($upTo <= $key) break;
            $low = $key;
            $key = null;
        }
        $low = $low ?: $key;
        if($preferLower && $upTo !== $key && $upTo < $key) $key = $low;
        return $key ? $array[$key] : null;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\ScatterWays
 ******************************************************************************/

class ScatterWays implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    private $minSymbols;
    public function __construct(array $definitions, array $payouts, array $config)
    {
        $this->minSymbols = $config['constraints']['minSymbols'];
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers =[]): array
    {
        $multipliers  = [];
        $symbols      = [];
        $clusters     = [];
        $defaultMask  = array_fill(0, count($screen), array_fill(0, count($screen[0]), 0));
        foreach ($screen as $x => $reel) {
            foreach ($reel as $y => $tile) {
                $symbols[$tile][] = [$x, $y];
            }
        }
        foreach ($symbols as $tileId => &$positions) {
            if (!isset($this->payouts[$tileId])) {
                continue;
            }
            foreach ($wilds as $wildId => $multiplier) {
                if ($tileId !== $wildId && isset($symbols[$wildId])) {
                    foreach ($symbols[$wildId] as [$x, $y]) {
                        $positions[$x.'-'.$y] = [$x, $y];
                    }
                }
            }
            if ($this->minSymbols > count($positions)) {
                continue;
            }
            $length = count(array_unique(array_column($positions, 0)));
            $payout = $this->payouts[$tileId][$length - 1] ?? 0;
            $payout = new Decimal($payout);
            if ($payout->isLarger(0)) {
                $currentMask = $defaultMask;
                $multiplier = 1;
                $tilesCnt = array_fill(0, count($screen), 0);
                foreach ($positions as [$x, $y]) {
                    $currentMask[$x][$y] = 1;
                    $tilesCnt[$x]++;
                    if (isset($multipliers[$x.'-'.$y])) {
                        $multiplier *= $multipliers[$x.'-'.$y];
                    }
                }
                $ways = array_product(array_filter($tilesCnt));
                $clusters[] = [
                    'id' => 0,
                    'tileId' => $tileId,
                    'start' => 0,
                    'length' => $length,
                    'payout' => $payout,
                    'definition' => [],
                    'multiplier' => $multiplier,
                    'positions' => $currentMask,
                    'ways' => $ways,
                    'type' => LineType::WAYS
                ];
            }
        }
        return $clusters;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\SuperLines
 ******************************************************************************/

class SuperLines implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $lines = [];
        foreach ($this->definitions as $id => $definition) {
            foreach ($this->find($definition, $screen, $wilds, $this->payouts) as $line) {
                $pays = new Decimal($line['payout']);
                if ($pays->isSmallerOrEqual(0)) continue;
                $line['id'] = $id;
                $lines[] = $line;
            }
        }
        return $lines;
    }
    protected function find(array $definition, array $screen, array $wilds, array $payouts): array
    {
        $lines = [];
        $lineTiles = [];
        foreach ($definition as $x => $y) $lineTiles[] = $screen[$x][$y];
        $tileIds        = array_count_values($lineTiles);
        $multipliers    = array_combine(array_keys($tileIds), array_fill(0, count($tileIds), 1));
        $wildsIncrement = array_intersect_key($tileIds, $wilds);
        foreach ($wildsIncrement as $wild => $count) {
            foreach ($tileIds as $tileId => $v) {
                $tileIds[$tileId] += $tileId !== $wild ? $count : 0;
                $multipliers[$tileId] *= $wilds[$wild] ** $count;
            }
        }
        foreach ($tileIds as $tileId => $streak) {
            if (isset($payouts[$tileId][$streak - 1])) {
                $lines[] = [
                    'tileId'     => $tileId,
                    'start'      => $this->getStartPos($lineTiles, $tileId, $wilds),
                    'length'     => $tileIds[$tileId],
                    'positions'  => $this->createSlots($wilds, $lineTiles, $tileId),
                    'payout'     => $payouts[$tileId][$streak - 1],
                    'definition' => $definition,
                    'multiplier' => $multipliers[$tileId],
                    'ways'       => 1,
                    'type'       => LineType::SUPER_LINES
                ];
            }
        }
        return $lines;
    }
    protected function getStartPos(array $lineTiles, int $tileId, array $wilds): int
    {
        $startPos = null;
        foreach ($lineTiles as $x => $tile) {
            if (isset($wilds[$tile])) {
                $startPos = $x;
            } elseif ($tile === $tileId) $startPos = $x;
            if (null !== $startPos) break;
        }
        return $startPos;
    }
    protected function createSlots(array $wilds, array $lineTiles, $tileId): array
    {
        $slots = [];
        foreach ($lineTiles as $x => $tile) {
            $slots[$x] = (int)($tile === $tileId);
            if (isset($wilds[$tile])) $slots[$x] = 1;
        }
        return $slots;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\Ways
 ******************************************************************************/

class Ways implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $ways = [];
        if (!isset($screen[0])) return $ways;
        $reelIndex  = 0;
        $roots = $this->getReelGroup($screen, $reelIndex, $wilds, $multipliers);
        $tree  = [];
        foreach ($roots as $tileId => $tileData) {
            if ($tileId === 0) {
                continue;
            }
            $tree[] = $this->createNode($tileId, $tileData, $reelIndex, $screen,$wilds, $multipliers);
        }
        foreach ($tree as $node) {
            $nodeResult = $this->extractNode($node);
            foreach ($nodeResult as $result) {
                $payout     = new Decimal($result['pays']);
                if ($payout->isSmallerOrEqual(0)) continue;
                $positions = array_fill(0, count($screen), array_fill(0, count($screen[0]), 0));
                foreach ($result['positions'] as [$x, $y]) $positions[$x][$y] = 1;
                $ways[] = [
                    'id'     => 0,
                    'tileId' => $result['tileId'],
                    'start'  => 0,
                    'length' => $result['length'],
                    'positions'    => $positions,
                    'rawPositions' => $result['positions'],
                    'payout'       => $payout,
                    'definition'   => [],
                    'multiplier'   => $result['multiplier'],
                    'ways'         => $result['ways'],
                    'type'         => LineType::WAYS
                ];
            }
        }
        return $ways;
    }
    protected function getReelGroup(array $screen, int $reel, array $wilds, array $waysMultipliers): array {
        $groupData     = [];
        foreach ($screen[$reel] as $index => $tile) {
            if ($tile === 0) continue;
            if (!isset($groupData[$tile])) $groupData[$tile] = [
                'positions'  => [],
                'multiplier' => $wilds[$tile] ?? 1,
                'isWild'     => isset($wilds[$tile])
            ];
            $groupData[$tile]['positions'][] = [$reel, $index, $waysMultipliers[$reel.'-'.$index] ?? 1];
        }
        return $groupData;
    }
    private function createNode(int $tileId, array &$tileData, int $reelIndex, array &$screen, array &$wilds, array &$waysMultipliers): array {
        if (!isset($screen[$reelIndex])) return [];
        $isWild     = isset($wilds[$tileId]);
        $multiplier = $tileData['multiplier'];
        $next       = [];
        $positions  = $tileData['positions'];
        $payout     = $this->payouts[$tileId][$reelIndex];
        $nextReelIndex = $reelIndex + 1;
        if (isset($screen[$nextReelIndex])) {
            $nextGroups = $this->getReelGroup($screen, $nextReelIndex, $wilds, $waysMultipliers);
            if (!$isWild) $nextGroups = $this->addWildsToGroup($tileId, $nextGroups, $wilds);
            foreach ($nextGroups as $neighbourTileId => $nextGroupData) {
                $neighbourIsWild = isset($wilds[$neighbourTileId]);
                $nextTileId = $tileId;
                if (!$isWild && !$neighbourIsWild && $tileId !== $neighbourTileId) continue;
                if ($isWild && !$neighbourIsWild) $nextTileId = $neighbourTileId;
                $next[] = $this->createNode($nextTileId, $nextGroupData, $nextReelIndex, $screen, $wilds, $waysMultipliers);
            }
        }
        return [
            'tileId'     => $tileId,
            'pays'       => $payout,
            'isWild'     => $isWild,
            'reelIndex'  => $reelIndex,
            'reelSize'   => count($screen[$reelIndex]),
            'ways'       => array_sum(array_column($positions, 2)),
            'multiplier' => $multiplier,
            'positions'  => $positions,
            'next'       => $next
        ];
    }
    protected function addWildsToGroup(int $currentTileId, array $reelGroup, array $wilds = []): array {
        if (!isset($reelGroup[$currentTileId])) return $reelGroup;
        foreach ($wilds as $id => $multiplier) {
            if (1 < $multiplier) continue;
            if (!isset($reelGroup[$id])) continue;
            $reelGroup[$currentTileId]['positions'] = array_merge($reelGroup[$currentTileId]['positions'], $reelGroup[$id]['positions']);
            unset($reelGroup[$id]);
        }
        return $reelGroup;
    }
    protected function extractNode(array $node, array &$stack = [], array &$result= []): array {
        $stack[] = $node;
        if (count($node['next']) <= 0) {
            $tileId     = $node['tileId'];
            $ways       = 1;
            $length     = 1;
            $multiplier = 1;
            $positions  = [];
            $pays       = $node['pays'];
            foreach ($stack as $element) {
                if (!$element['isWild']) $tileId = $element['tileId'];
                $positions   = array_merge($positions, $element['positions']);
                $ways       *= $element['ways'];
                $multiplier *= $element['multiplier'];
            }
            $result[] = [
                'tileId'     => $tileId,
                'ways'       => $ways,
                'multiplier' => $multiplier,
                'pays'       => $pays,
                'length'     => $length + $node['reelIndex'],
                'positions'  => $positions
            ];
        }
        foreach ($node['next'] as $nextNode) {
            $this->extractNode($nextNode, $stack, $result);
            array_pop($stack);
        }
        return $result;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\WaysAW
 ******************************************************************************/

class WaysAW implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $ways = [];
        $calculator = new Ways($this->definitions, $this->payouts);
        $matrixPositions = array_fill(0, count($screen), array_fill(0, count($screen[0]), 0));
        for ($x=0, $xMax = count($screen); $x < $xMax; $x ++) {
            $tmpWays = $calculator->calculate(array_slice($screen, $x, count($screen)), $wilds, $multipliers);
            foreach ($tmpWays as $key => $tmpWay) {
                $tmpPositions = $matrixPositions;
                foreach ($tmpWay['rawPositions'] as [$x1, $y1]) {
                    $screenTileId = $screen[$x1+$x][$y1];
                    if (!isset($wilds[$screenTileId])) $screen[$x1+$x][$y1] = 0;
                    $tmpPositions[$x1+$x][$y1] = 1;
                }
                $tmpWays[$key]['start']     = $x;
                $tmpWays[$key]['positions'] = $tmpPositions;
                $ways[] = $tmpWays[$key];
            }
        }
        return $ways;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\Strategy\ScatterPays
 ******************************************************************************/

class ScatterPays implements LineStrategyInterface
{
    protected $definitions;
    protected $payouts;
    public function __construct(array $definitions, array $payouts)
    {
        $this->definitions = $definitions;
        $this->payouts = $payouts;
    }
    public function calculate(array $screen, array $wilds, array $multipliers): array
    {
        $symbols      = [];
        $clusters     = [];
        $defaultMask  = array_fill(0, count($screen), array_fill(0, count($screen[0]), 0));
        foreach ($screen as $x => $reel) {
            foreach ($reel as $y => $tile) {
                $symbols[$tile][] = [$x, $y];
            }
        }
        foreach ($symbols as $tileId => &$positions) {
            foreach ($wilds as $wildId => $multiplier) {
                if ($tileId !== $wildId && isset($symbols[$wildId])) {
                    foreach ($symbols[$wildId] as [$x, $y]) {
                        $positions[] = [$x, $y];
                    }
                }
            }
            $length = count($positions);
            $payout = Calculator::getUpToLower($this->payouts[$tileId], $length);
            if ($payout > 0) {
                $currentMask = $defaultMask;
                $multiplier = 1;
                foreach ($positions as [$x, $y]) {
                    $currentMask[$x][$y] = 1;
                    if (isset($multipliers[$x.'-'.$y])) {
                        $multiplier *= $multipliers[$x.'-'.$y];
                    }
                }
                $clusters[] = [
                    'id' => 0,
                    'tileId' => $tileId,
                    'start' => 0,
                    'length' => $length,
                    'payout' => $payout,
                    'definition' => [],
                    'multiplier' => $multiplier,
                    'positions' => $currentMask,
                    'ways' => 1,
                    'type' => LineType::SCATTER_PAYS
                ];
            }
        }
        return $clusters;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Tile
 ******************************************************************************/

interface Tile {
    const TYPE_NORMAL  = 'normal';
    const TYPE_MYSTERY = 'mystery';
    const TYPE_WILD    = 'wild';
    const TYPE_SCATTER = 'scatter';
    const TYPE_JACKPOT = 'jackpot';
}


/*******************************************************************************
 * Class: Math\Games\Slot\Lines\LineCalculator
 ******************************************************************************/

class LineCalculator
{
    protected $strategy;
    protected $wilds;
    public function __construct(LineStrategyInterface $strategy, array $wilds=[])
    {
        $this->strategy = $strategy;
        $this->wilds    = $wilds;
    }
    public function calcLines(array $screen, array $wilds=[], array $multipliers =[]): array
    {
        $lines = [];
        if (!$wilds) $wilds = $this->wilds;
        foreach ($this->strategy->calculate($screen, $wilds, $multipliers) as $data) {
            $lines[] =  new Line(
                $data['id'],
                $data['tileId'],
                $data['start'],
                $data['length'],
                $data['positions'],
                $data['payout'],
                $data['definition'],
                $data['multiplier'],
                $data['ways'],
                $data['type']
            );
        }
        usort($lines, static function (Line $line1, Line $line2) {
            return $line1->getId() > $line2->getId();
        });
        return $lines;
    }
    public static function create(array $config): LineCalculator
    {
        $wilds = [];
        $pays = array_combine(array_column($config['tiles'], 'id'), array_column($config['tiles'], 'pays'));
        foreach ($config['tiles'] as $t) {
            if ($t['type'] === Tile::TYPE_WILD) {
                $wilds[$t['id']] = $t['multiplier'] ?? 1;
            }
        }
        $definitions = $config['lines'];
        switch ($config['direction'])
        {
            case LineType::LTR:
                return new self(new LTR($definitions, $pays), $wilds);
            case LineType::RTL:
                return new self(new RTL($definitions, $pays), $wilds);
            case LineType::BW:
                return new self(new BW($definitions, $pays), $wilds);
            case LineType::AW:
                return new self(new AW($definitions, $pays), $wilds);
            case LineType::CLUSTER:
                return new self(new Cluster($definitions, $pays), $wilds);
            case LineType::WAYS:
                return new self(new Ways($definitions, $pays), $wilds);
            case LineType::WAYS_AW:
                return new self(new WaysAW($definitions, $pays), $wilds);
            case LineType::SUPER_LINES:
                return new self(new SuperLines($definitions, $pays), $wilds);
            case LineType::SCATTER_PAYS:
                return new self(new ScatterPays($definitions, $pays), $wilds);
            case LineType::SCATTER_WAYS:
                return new self(new ScatterWays($definitions, $pays, $config), $wilds);
            default:
                throw new GameException('Not implemented!');
        }
    }
}


/*******************************************************************************
 * Class: Math\Games\Utils\Chance
 ******************************************************************************/

class Chance {
    protected $rng;
    public function __construct($rng) {
        $this->rng = $rng;
    }
    public function weighted(array $chances) {
        $random = $this->random(1, 10000000);
        $tmp = $random;
        $sum = array_sum($chances);
        if (((int) round($sum * 100000)) !== 10000000) {
            throw new GameException('The total chance is not equal to 100.000% (' . (((int) round($sum * 100000)) / 100000) . ').');
        }
        foreach ($chances as $index => $chance) {
            $chance = (int) round(((float) $chance) * 100000);
            if ($chance >= $tmp) return $index;
            else $tmp -= $chance;
        }
        throw new GameException('Chances is returning null.');
    }
    public function index(array $series) {
        return (int) $this->random(0, count($series) - 1);
    }
    public function indexes(array $series, $count) {
        $keys = array_keys($series);
        $length = count($keys);
        if ($count >= $length) return $keys;
        if (!$length) return [];
        $indexes = [];
        for ($i = 0; $i < $count; $i ++) {
            $index = (int) $this->random(0, $length - 1);
            $indexes[] = $keys[$index];
            array_splice($keys, $index, 1);
            $length --;
            if ($length <= 0) break;
        }
        return $indexes;
    }
    public function series(array $series, $count = 1) {
        if ($count === 1) return $series[$this->index($series)];
        $indexes = $this->indexes($series, $count);
        $result = [];
        foreach ($indexes as $index) $result[] = $series[$index];
        return $result;
    }
    public function single($percent, int $scale = 100) {
        if ($scale < 100 || 100000 < $scale) {
            throw new GameException('Scale must be in range(100, 100000)');
        }
        return ((int) round($percent * $scale)) >= $this->random(1, (100 * $scale));
    }
    public function random($min, $max){
        if (null === $this->rng) throw new GameException('RNG is not provided');
        return $this->rng->random($min, $max);
    }
    public function shuffle(array $array) {
        $array = array_values($array);
        $counter = count($array);
        while ($counter > 0) {
            $index = $this->random(0, $counter - 1);
            $counter --;
            $temp = $array[$counter];
            $array[$counter] = $array[$index];
            $array[$index] = $temp;
        }
        return $array;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\ActionInterface
 ******************************************************************************/

interface ActionInterface
{
    public function setNext(ActionInterface $nextAction, string $name): ActionInterface;
    public function setActive(string $active): ActionInterface;
    public function handle(Result $result, string $active): ?Result;
}


/*******************************************************************************
 * Class: Math\Games\Slot\ActionFormatInterface
 ******************************************************************************/

interface ActionFormatInterface
{
    public function format(Result $result, string $name): array;
}


/*******************************************************************************
 * Class: Math\Games\Slot\Helpers\BufferHelper
 ******************************************************************************/

class BufferHelper
{
    public static function getScreen(array $buffer, int $offset): array
    {
        $rows = count($buffer[0]) - 2 * $offset;
        foreach ($buffer as $x => $reel) {
            $buffer[$x] = array_slice($reel, $offset, $rows);
        }
        return $buffer;
    }
    public static function getTop(array $buffer, int $offset): array
    {
        foreach ($buffer as $x => $reel) {
            $buffer[$x] = array_slice($reel, 0, $offset);
        }
        return $buffer;
    }
    public static function getBottom(array $buffer, int $offset): array
    {
        foreach ($buffer as $x => $reel) $buffer[$x] = array_slice($reel, -$offset);
        return $buffer;
    }
    public static function getTiles(array $buffer): array {
        $tilesOnScreen = [];
        foreach ($buffer as $x => $reel) {
            foreach ($reel as $y => $tile) {
                if (!isset($tilesOnScreen[$tile])) $tilesOnScreen[$tile] = [];
                $tilesOnScreen[$tile][$x . '-' . $y] = [$x, $y];
            }
        }
        return $tilesOnScreen;
    }
    public static function getPositions(array $buffer, array $skipTiles=[]): array
    {
        $positions = [];
        for ($i=0, $iMax = count($buffer); $i < $iMax; $i++) {
            for ($j=0, $jMax = count($buffer[0]); $j < $jMax; $j++) {
                if (in_array($buffer[$i][$j], $skipTiles, true)) continue;
                $positions[$i.'-'.$j] = [$i, $j, $buffer[$i][$j]];
            }
        }
        return $positions;
    }
    public static function getPositionsByType(array $buffer, int $type, int $offset): array
    {
        $rowsMax = count($buffer[0]);
        $rows = $rowsMax - 2 * $offset;
        switch ($type) {
            case Buffer::SCREEN:
                $yStart = $offset;
                $yEnd  = $offset + $rows;
                break;
            case Buffer::TOP:
                $yStart = 0;
                $yEnd = $offset;
                break;
            case Buffer::BOTTOM:
                $yStart = $offset + $rows;
                $yEnd = $rowsMax;
                break;
            default:
                $yStart = 0;
                $yEnd = $rowsMax;
                break;
        }
        $positions = [];
        for ($x=0, $xMax = count($buffer); $x < $xMax; $x++) {
            for ($y=$yStart; $y < $yEnd; $y++) {
                $positions[$x.'-'.$y] = [$x, $y, $buffer[$x][$y]];
            }
        }
        return $positions;
    }
    public static function filterScreenPositions(array $positions, int $rows, int $offset): array
    {
        return array_filter($positions, function ($pos) use ($offset, $rows) {
            return $pos[1] >= $offset && $pos[1] < $offset + $rows;
        });
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Buffer
 ******************************************************************************/

class Buffer
{
    const ALL    = 3;
    const TOP    = 0;
    const SCREEN = 1;
    const BOTTOM = 2;
    protected $rows;
    protected $cols;
    protected $offset;
    protected $reels;
    protected $reelCategory;
    protected $reelIndex;
    protected $stopPositions;
    public static function create(int $rows, int $offset, array $reels, string $reelCategory, int $reelIndex, array $stops): Buffer
    {
        return new self($rows, $offset, $reels, $reelCategory, $reelIndex, $stops);
    }
    public static function createFrom(Buffer $otherBuffer):self
    {
        return new self($otherBuffer->getRows(), $otherBuffer->getOffset(), $otherBuffer->getReels(), $otherBuffer->getReelCategory(), $otherBuffer->getReelIndex(), $otherBuffer->getStopPositions());
    }
    private function __construct(int $rows, int $offset, array $reels, string $reelCategory, int $reelIndex, array $stops)
    {
        $this->cols   = count($reels);
        $this->rows   = $rows;
        $this->offset = $offset;
        $this->reels  = $reels;
        $this->reelCategory = $reelCategory;
        $this->reelIndex = $reelIndex;
        $this->stopPositions = $stops;
    }
    public function getCols():int
    {
        return $this->cols;
    }
    public function getRows(): int
    {
        return $this->rows;
    }
    public function getOffset(): int
    {
        return $this->offset;
    }
    public function getReelCategory(): string
    {
        return $this->reelCategory;
    }
    public function getReelIndex(): int
    {
        return $this->reelIndex;
    }
    public function getBy(int $type):array
    {
        if (!in_array($type, [self::SCREEN, self::ALL], true)) {
            throw new GameException('[Buffer] Not supported buffer type['.$type.']');
        }
        return $type === self::SCREEN ? $this->getScreen() : $this->getReels();
    }
    public function getReels(): array
    {
        return $this->reels;
    }
    public function getScreen(): array
    {
        $buffer = [];
        foreach ($this->reels as $x => $reel) {
            $buffer[$x] = array_slice($reel, $this->offset, $this->rows);
        }
        return $buffer;
    }
    public function getStopPositions(): array
    {
        return $this->stopPositions;
    }
    public function setTile(int $x, int $y, int $id): void
    {
        $this->reels[$x][$y] = $id;
    }
    public function setTileToScreen(int $x, int $y, int $id): void
    {
        $this->setTile($x, $y + $this->offset, $id);
    }
    public function setTiles(array $tiles) {
        foreach ($tiles as $tile) {
            $this->setTile(...$tile);
        }
    }
    public function build(): array
    {
        $buffer = [];
        for ($x=0, $xMax = count($this->reels); $x < $xMax; $x++) {
            $buffer[$x] = [
                array_slice($this->reels[$x], 0, $this->offset),
                array_slice($this->reels[$x], $this->offset, $this->rows),
                array_slice($this->reels[$x], $this->offset+$this->rows, $this->offset),
            ];
        }
        return  $buffer;
    }
    public function replace(array $buffer, int $type=Buffer::ALL): void
    {
        $offset = $type === self::SCREEN ? $this->offset : 0;
        foreach ($buffer as $x => $rows) {
            foreach ($rows as $y => $item) {
                $this->reels[$x][$offset + $y] = $item;
            }
        }
    }
    public function getPositions(array $skipTiles=[]): array
    {
        return BufferHelper::getPositions($this->reels, $skipTiles);
    }
    public function findTiles(int ... $tileIds): array
    {
        return Calculator::get2dPositions($this->reels, $tileIds);
    }
    public function filterPositions(array $positions, int $type): array
    {
        switch ($type) {
            case Buffer::SCREEN:
                $yStart = $this->offset;
                $yEnd  = $this->offset + $this->rows;
                break;
            case Buffer::TOP:
                $yStart = 0;
                $yEnd = $this->offset;
                break;
            case Buffer::BOTTOM:
                $yStart = $this->offset + $this->rows;
                $yEnd = $this->rows + (2 * $this->offset);
                break;
            default:
                $yStart = 0;
                $yEnd = $this->rows + (2 * $this->offset);
                break;
        }
        return array_filter($positions, function ($pos) use ($yStart, $yEnd) {
            [$x, $y] = $pos;
            return $x >= 0
                && $x < $this->cols
                && $y >= $yStart
                && $y < $yEnd;
        });
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Trigger
 ******************************************************************************/

interface Trigger
{
    const NONE             = 0;
    const POSITIONS        = 1;
    const DATA             = 2;
    const BOTH             = 3;
}


/*******************************************************************************
 * Class: Math\Games\Slot\ResultInterface
 ******************************************************************************/

interface ResultInterface
{
    public function build(): array;
    public function getBuffer(): Buffer;
    public function getData(string $name);
    public function getDepth():int;
    public function getStake(): Decimal;
}


/*******************************************************************************
 * Class: Math\Games\Slot\Result
 ******************************************************************************/

class Result implements ResultInterface
{
    protected $stake = 0;
    protected $lines = [];
    protected $multiplier = 1;
    protected $data = [];
    protected $wins = [];
    protected $state = [];
    protected $dataFormat;
    protected $buffer;
    protected $depth;
    protected $positions;
    protected $mode;
    public static function create(Decimal $stake, Buffer $buffer, $depth=1): self
    {
        $obj             = new static();
        $obj->buffer     = Buffer::createFrom($buffer);
        $obj->positions  = [];
        $obj->data       = [];
        $obj->depth      = $depth;
        $obj->state      = [];
        $obj->stake      = $stake;
        $obj->wins       = [];
        $obj->dataFormat = [];
        $obj->lines      = [];
        $obj->mode       = 'Normal';
        return $obj;
    }
    public static function copy(Result $otherResult):self
    {
        $obj             = new static();
        $obj->buffer     = Buffer::createFrom($otherResult->getBuffer());
        $obj->data       = $otherResult->data;
        $obj->positions  = $otherResult->positions;
        $obj->depth      = $otherResult->depth;
        $obj->state      = $otherResult->state;
        $obj->stake      = $otherResult->stake;
        $obj->wins       = $otherResult->wins;
        $obj->dataFormat = $otherResult->dataFormat;
        $obj->lines      = $otherResult->lines;
        $obj->mode       = $otherResult->mode;
        return $obj;
    }
    private function __construct() {}
    final public function pushState(?array $state): void
    {
        if ($this->state) {
            throw new GameException('Unhandled game state');
        }
        $this->state = $state;
    }
    final public function popState(): ?array
    {
        $state = $this->state;
        $this->state = [];
        return $state;
    }
    final public function hasState(): bool
    {
        return !empty($this->state);
    }
    public function getDepth(): int
    {
        return $this->depth;
    }
    public function setDepth(int $newDepth): void
    {
        $this->depth = $newDepth;
    }
    public function setBuffer(Buffer $newBuffer)
    {
        $this->buffer = $newBuffer;
    }
    public function setMultiplier($multiplier): void
    {
        $this->multiplier = $multiplier;
    }
    public function setLines(array $lines, $name = 'lines'): void
    {
        $this->lines = $lines;
        $win = new Decimal();
        foreach ($lines as $line) {
            $win = $win->add($line->calcWinnings()->mul($this->stake));
        }
        if ($name) $this->setWin($name, $win);
    }
    public function setWin($name, Decimal $amount, $total = true, $multiply = true, $recursive = false): void
    {
        $this->wins[$name] = [
            'amount'    => $amount->toString(),
            'multiply'  => $multiply,
            'total'     => $total,
            'recursive' => $recursive
        ];
    }
    public function resetWin($name = 'lines'): void
    {
        if (isset ($this->wins[$name])) unset($this->wins[$name]);
    }
    public function resetData($name): void
    {
        if (isset ($this->data[$name])) unset($this->data[$name]);
        $this->resetDataFormat($name);
    }
    public function setData(string $name, $data): void
    {
        $this->data[$name] = $data;
    }
    public function getPositions(bool $clear = true): array
    {
        $positions = $this->positions;
        if ($clear) $this->positions = [];
        return $positions;
    }
    public function setPositions(array $positions): void
    {
        $this->positions = $positions;
    }
    public function setDataFormat(string $name, ActionFormatInterface $format): void
    {
        $this->dataFormat[$name] = $format;
    }
    public function resetDataFormat(string $name): void
    {
        if (isset ($this->dataFormat[$name])) unset($this->dataFormat[$name]);
    }
    public function addPosition(array $position): void
    {
        $this->positions[$position[0].'-'.$position[1]] = $position;
    }
    public function getMode(): string
    {
        return $this->mode;
    }
    public function setMode(string $newMode): void
    {
        $this->mode = $newMode;
    }
    public function calcTotalWin($recursive = false): Decimal
    {
        $total = new Decimal();
        foreach ($this->wins as $win) {
            if (!$win['total'] || (!$recursive && $win['recursive'])) continue;
            $amount = new Decimal($win['amount']);
            if ($win['multiply']) $amount = $amount->mul($this->multiplier);
            $total = $total->add($amount);
        }
        $tmp = new Decimal($total->toString());
        $ref = new Decimal($tmp->mul(100)->toString(), 0);
        $tmp = $tmp->mul(100);
        if (!$tmp->sub($ref)->isEqual(0))
            throw new GameException('TotalWin cannot contain more than two decimal places. Stake:('.$this->stake->toString().'), TotalWin: ('.$total->toString().')');
        return $total;
    }
    public function getStake(): Decimal
    {
        return $this->stake;
    }
    public function getBuffer(): Buffer
    {
        return $this->buffer;
    }
    public function getLines(): array
    {
        return $this->lines;
    }
    public function getData(string $name) {
        return $this->data[$name] ?? null;
    }
    public function getWin($type = null): Decimal
    {
        if (!$type) return $this->calcTotalWin();
        if (!isset ($this->wins[$type])) return new Decimal();
        $amount = new Decimal($this->wins[$type]['amount']);
        if ($this->wins[$type]['multiply']) $amount = $amount->mul($this->multiplier);
        return $amount;
    }
    public function getWinsArray($total = true): array
    {
        $wins = [];
        $totalWin = new Decimal();
        $multiplied = new Decimal();
        foreach ($this->wins as $name => $win) {
            $amount = new Decimal($win['amount']);
            $wins[lcfirst($name)] = $amount->toString();
            if ($this->multiplier > 1 && $win['multiply']) {
                $tmp = $amount->mul($this->multiplier - 1);
                $multiplied = $multiplied->add($tmp);
                $amount = $amount->add($tmp);
            }
            if ($total && $win['total']) $totalWin = $totalWin->add($amount);
        }
        if ($this->multiplier > 1) $wins['multiplier'] = $multiplied->toString();
        if ($total) $wins['total'] = $totalWin->toString();
        return $wins;
    }
    public function getLinesArray(array $lines = null): array
    {
        $lines = $lines ?: $this->lines;
        $linesArray = [];
        foreach ($lines as $line) {
            $line->setGlobalMultiplier($this->multiplier);
            $linesArray[] = $line->export($this->stake);
        }
        return $linesArray;
    }
    public function build(bool $withState=true): array
    {
        $winsArray      = $this->getWinsArray();
        $winMultipliers = [];
        foreach ($winsArray as $winType => $winValue) {
            $winMultipliers[$winType] = (new Decimal($winValue))->div($this->stake)->toString();
        }
        $array = [
            'win'             => $winsArray,
            'winsMultipliers' => $winMultipliers,
            'stake'           => (string) $this->stake
        ];
        if ($withState && $this->state && !empty($this->state)) {
            $array['state'] = $this->state;
        }
        $array['gameData'] = [
            'multiplier' => $this->multiplier ?: null,
            'winLines'   => $this->getLinesArray(),
            'spinMode'   => $this->mode
        ];
        foreach ($this->data as $name => $data) {
            if (isset($this->dataFormat[$name])) {
                $array['gameData'][lcfirst($name)] = $this->dataFormat[$name]->format($this, $name);
            }
            else {
                $array['gameData'][lcfirst($name)] = $data;
            }
        }
        $array['gameData']['reelsBuffer'] = $this->buffer->build();
        return $array;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Action
 ******************************************************************************/

abstract class Action implements ActionInterface, ActionFormatInterface
{
    const SOURCE_DATA      = 0;
    const STORE_DATA       = 1;
    const SKIP_SOURCE      = 2;
    const BREAK_ON_FAIL    = 3;
    const SET_BUFFER       = 4;
    const SET_WIN          = 5;
    const PREFERRED_BUFFER = 6;
    protected $game;
    protected $name;
    protected $random;
    protected $next;
    protected $lineCalculator;
    protected $dataSource;
    protected $activeConfiguration;
    protected $configurations;
    protected $nextArr = [];
    protected $triggers = [
        Action::BREAK_ON_FAIL    => true,
        Action::PREFERRED_BUFFER => Buffer::ALL,
        Action::SET_BUFFER       => false,
        Action::SET_WIN          => true,
        Action::SOURCE_DATA      => Trigger::POSITIONS,
        Action::STORE_DATA       => Trigger::DATA,
        Action::SKIP_SOURCE      => Trigger::POSITIONS
    ];
    private $initialTriggers;
    final public function __construct(Chance $random, array $config) {
        $this->next            = null;
        $this->random          = $random;
        $this->activeConfiguration = key($config);
        $this->configurations      = $config;
        $this->nextArr = array_combine(array_keys($config), array_fill(0, count($config), null));
    }
    final public function setActive(string $newActive): ActionInterface
    {
        if (!isset($this->configurations[$newActive])) {
            throw new GameException('Missing Configuration for ' . $newActive . '('. static::class .')');
        }
        $this->activeConfiguration = $newActive;
        $this->setup($this->configurations[$newActive]);
        if (null === $this->initialTriggers) {
            $this->initialTriggers = $this->triggers;
        }
        $triggers = $this->initialTriggers;
        if (isset($this->configurations[$newActive]['triggers'])) {
            foreach ($this->configurations[$newActive]['triggers'] as $k => $v) $triggers[$k] = $v;
        }
        $this->setTrigger($triggers);
        if (isset($this->nextArr[$newActive])) {
            $this->nextArr[$newActive]->setActive($newActive);
        }
        $this->next = $this->nextArr[$newActive] ?? null;
        $this->name = ucfirst($this->configurations[$newActive]['name'] ?? $this->activeConfiguration);
        return $this;
    }
    public function setNext(ActionInterface $nextAction, string $name): ActionInterface
    {
        if (isset($this->nextArr[$name])) {
            throw new GameException('[Action] next is already set');
        }
        $this->nextArr[$name] = $nextAction;
        return $this->nextArr[$name];
    }
    public function setTrigger(array $newTriggers): void
    {
        foreach ($newTriggers as $newTrigger => $newTriggerValue) {
            $this->triggers[$newTrigger] = $newTriggerValue;
        }
    }
    public function handle(Result $result, string $active): ?Result
    {
        $this->setActive($active);
        $Result = Result::copy($result);
        $Result = $this->operation($Result);
        if ($Result && $this->next) {
            return $this->next->handle($Result, $active);
        }
        if (false === $this->triggers[Action::BREAK_ON_FAIL] && !$Result) {
            return $result;
        }
        return $Result;
    }
    public function format(Result $result, string $name): array
    {
        return $result->getData($name);
    }
    public function getName():string
    {
        return $this->name;
    }
    protected function setup(array $config) {}
    protected function getSkipPositions(Result $result): array
    {
        $skip = [];
        switch ($this->triggers[Action::SKIP_SOURCE]) {
            case Trigger::DATA:
                $skip = $result->getData($this->dataSource) ?? [];
                break;
            case Trigger::POSITIONS:
                $skip = $result->getPositions();
                break;
            case Trigger::NONE:
                break;
        }
        return $skip;
    }
    protected function getBuffer(Result $result): array
    {
        return $result->getBuffer()->getReels();
    }
    protected function getInputData(Result $result) {
        switch ($this->triggers[Action::SOURCE_DATA]) {
            case Trigger::POSITIONS:
                return $result->getPositions();
            case Trigger::DATA:
                return $result->getData($this->dataSource);
            default:
                throw new GameException('Improperly configured input data source.');
        }
    }
    protected function saveData(Result $result, array $data): void
    {
        switch ($this->triggers[Action::STORE_DATA]) {
            case Trigger::POSITIONS:
                $result->setPositions($data);
                break;
            case Trigger::DATA:
                $result->setData($this->name, $data);
                $result->setDataFormat($this->name, $this);
                break;
            case Trigger::BOTH:
                $result->setData($this->name, $data);
                $result->setPositions($data);
                $result->setDataFormat($this->name, $this);
                break;
        }
    }
    protected function saveBuffer(Result $result, array $buffer): void
    {
        if (true === $this->triggers[self::SET_BUFFER]) {
            $result->getBuffer()->replace($buffer);
        }
    }
    abstract protected function operation(Result $result): ?Result;
}


/*******************************************************************************
 * Class: Math\Games\Slot\Actions\FeatureChance
 ******************************************************************************/

class FeatureChance extends Action
{
    protected $name = 'Chance';
    private $chance;
    public static function getConfig(): array
    {
        return [
            'Chance' => [
                1000 => 50
            ]
        ];
    }
    protected function setup(array $config):void
    {
        if (!$config) {
            throw new GameException('Chance: Invalid config. Missing \'chance\' key');
        }
        $this->chance = $config;
    }
    public function operation(Result $result): ?Result
    {
        $chance = Calculator::getUpTo($this->chance, $result->getDepth());
        if ($this->random->single($chance)) {
            return $result;
        }
        return null;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Actions\Strategies\RandomPositionsStrategyInterface
 ******************************************************************************/

interface RandomPositionsStrategyInterface
{
    public function setup(array $config): void;
    public function getPositions(array $buffer, array $skipPositions = [], int $depth=1): ?array;
}


/*******************************************************************************
 * Class: Math\Games\Slot\Actions\RandomPositions
 ******************************************************************************/

class RandomPositions extends Action
{
    protected $name = 'RandomPositions';
    private $strategy;
    public static function getConfig()
    {
        return [];
    }
    protected function setup(array $config)
    {
        $this->triggers[Action::STORE_DATA] = Trigger::POSITIONS;
        $this->strategy->setup($config);
    }
    public function setStrategy(RandomPositionsStrategyInterface $strategy): void
    {
        if ($this->strategy) {
            throw new GameException('Strategy is already set!');
        }
        $this->strategy = $strategy;
    }
    protected function operation(Result $result): ?Result
    {
        $buffer = $this->getBuffer($result);
        $skip = $this->getSkipPositions($result);
        $depth = $result->getDepth();
        if ($positions = $this->strategy->getPositions($buffer, $skip, $depth)) {
            $this->saveData($result, $positions);
            return $result;
        }
        return null;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Actions\RandomTiles
 ******************************************************************************/

class RandomTiles extends Action
{
    protected $name = 'RandomTiles';
    protected $tileIds;
    protected $randomize;
    protected $unique;
    public static function getConfig(): array
    {
        return [
            'RandomTiles' => [
                'tileIds' => [1000 => [10 => 100]],
                'triggers'  => [
                    Action::STORE_DATA   => Trigger::POSITIONS,
                    Action::SOURCE_DATA  => Trigger::POSITIONS,
                    Action::SET_BUFFER   => false
                ]
            ]
        ];
    }
    protected function setup(array $config)
    {
        $this->tileIds    = $config['tileIds'];
        $this->dataSource = $config['dataSource'] ?? 'RandomTiles';
        $this->randomize  = $config['randomize'] ?? false;
        $this->unique     = $config['unique'] ?? false;
    }
    public function format(Result $result, string $name): array
    {
        if ($data = $result->getData($name)) {
            $positions = [];
            foreach ($data as $k => [$x, $y, $tileId]) {
                $y -= $result->getBuffer()->getOffset();
                if ($y >= 0 && $y < $result->getBuffer()->getRows()) {
                    $positions[] = [
                        'reel' => $x,
                        'index' => $y,
                        'tileId' => $tileId
                    ];
                }
            }
            return $positions;
        }
        return [];
    }
    protected function operation(Result $result): ?Result
    {
        $tileIds   = Calculator::getUpTo($this->tileIds, $result->getDepth());
        $positions = $this->getInputData($result);
        $buffer    = $this->getBuffer($result);
        $tileId    = $this->random->weighted($tileIds);
        if (true === $this->unique && count($positions) > count($tileIds)) {
            $positions = [];
        }
        if ($this->triggers[Action::BREAK_ON_FAIL] && 1 > count($positions)) {
            return null;
        }
        foreach ($positions as $k => [$x, $y]) {
            if (true === $this->randomize) {
                $tileId = $this->random->weighted($tileIds);
            }
            if (true === $this->unique) {
                unset($tileIds[$tileId]);
                if ($tileIds) $tileIds = Calculator::distributeWeights($tileIds);
            }
            $buffer[$x][$y]  = $tileId;
            $positions[$k][2] = $tileId;
        }
        $this->saveData($result, $positions);
        $this->saveBuffer($result, $buffer);
        return $result;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Actions\Strategies\RandomPositionsStrategy
 ******************************************************************************/

class RandomPositionsStrategy implements RandomPositionsStrategyInterface
{
    protected $random;
    private $count;
    private $reels;
    private $skipTiles;
    private $breakOnSkip;
    public function __construct(Chance $random)
    {
        $this->random = $random;
    }
    public static function getConfig(): array
    {
        return [
            'RandomPositions' => [
                'skipTiles'   => [
                    1000 => []
                ],
                'count' => [
                    1000 => [
                        2  => 27,
                        4  => 30,
                        6  => 20,
                        8  => 10,
                        10 =>  8,
                        12 =>  4,
                        14 =>  1,
                    ],
                ],
                'breakOnSkip' => [ 1000 => false ],
                'reels' => [
                    1000 => [
                        ['reels' => [0,1,2,3,4], 'chance' => 100, 'sameRow' => false, 'rows' => [[0,8]]],
                    ]
                ],
                'triggers' => [
                    Action::SKIP_SOURCE => Trigger::POSITIONS,
                    Action::STORE_DATA => Trigger::POSITIONS,
                ]
            ]
        ];
    }
    public function setup(array $config): void
    {
        $this->count       = $config['count'];
        $this->reels       = $config['reels'];
        $this->skipTiles   = $config['skipTiles'] ?? [10000 => []];
        $this->breakOnSkip = $config['breakOnSkip'] ?? [10000 => true];
    }
    public function getPositions(array $buffer, array $skipPositions = [], int $depth = 1): ?array
    {
        $availablePositions = [];
        $reels              = [];
        $skipTiles          = Calculator::getUpTo($this->skipTiles, $depth);
        $breakOnSkip        = Calculator::getUpTo($this->breakOnSkip, $depth);
        $config             = Calculator::getUpTo($this->reels, $depth);
        $i                  = $this->random->weighted(array_column($config, 'chance'));
        $count              = $this->random->weighted(Calculator::getUpTo($this->count, $depth));
        $config             = $config[$i];
        $availableReels     = $config['reels'];
        $sameRow            = array_key_exists('sameRow', $config) ? $config['sameRow'] : false;
        [$startY, $endY]    = $this->random->series($config['rows']);
        if ($breakOnSkip && array_intersect($availableReels, array_column(Calculator::get2dPositions($buffer, $skipTiles), 0))) {
            return null;
        }
        foreach ($availableReels as $x) {
            $reels[$x] = [$startY, $endY];
            if (false === $sameRow) {
                [$startY, $endY] = $this->random->series($config['rows']);
            }
        }
        foreach ($reels as $x => [$startRow, $endRow]) {
            for ($y=$startRow; $y <= $endRow; $y++) {
                if (!isset($skipPositions[$x.'-'.$y]) && !in_array($buffer[$x][$y], $skipTiles, true)) {
                    $availablePositions[$x.'-'.$y] = [$x, $y];
                }
            }
        }
        if ($availablePositions && $count <= count($availablePositions)) {
            $positions          = [];
            $availablePositions = array_slice($this->random->shuffle($availablePositions), 0, $count);
            foreach ($availablePositions as [$x,$y]) $positions[$x.'-'.$y] = [$x, $y, $buffer[$x][$y]];
            return $positions;
        }
        return null;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Actions\Strategies\ReelPositionsStrategy
 ******************************************************************************/

class ReelPositionsStrategy implements RandomPositionsStrategyInterface
{
    protected $random;
    protected $skipTiles = [];
    protected $count = [];
    protected $sameCount = [];
    protected $reels = [];
    protected $connected;
    protected $connectedRows;
    protected $breakOnSkip;
    protected $grouped;
    protected $chanceReverse;
    public function __construct(Chance $random)
    {
        $this->random = $random;
    }
    public static function getConfig(): array
    {
        return [
            'ReelPositions' => [
                'skipTiles' => [
                    1000 => [0, 10,11,12,13]
                ],
                'sameCount' => [
                    1000 => [1 => 100]
                ],
                'reels' => [
                    1000 => [
                        ['reels' => [0,1,2,3,4], 'chance' => 100, 'sameRow' => false, 'rows' => [[0, 8]]],
                    ],
                ],
                'breakOnSkip' => [1000 => false],
                'triggers' => [
                    Action::STORE_DATA  => Trigger::POSITIONS,
                    Action::SKIP_SOURCE => Trigger::POSITIONS
                ]
            ]
        ];
    }
    public function setup(array $config): void
    {
        $this->count = $config['count'] ?? null;
        $this->sameCount = $config['sameCount'] ?? null;
        $this->reels = $config['reels'] ?? [];
        $this->skipTiles = $config['skipTiles'] ?? [];
        $this->connected = $config['connected'] ?? [10000 => false];
        $this->connectedRows = $config['connectedRows'] ?? [10000 => false];
        $this->breakOnSkip = $config['breakOnSkip'] ?? [10000 => true];
        $this->chanceReverse = $config['chanceReverse'] ?? 50;
        $this->grouped = isset($config['grouped']) ? (bool)$config['grouped'] : false;
    }
    public function getPositions(array $buffer, array $skipPositions = [], int $depth = 1): ?array
    {
        $featureConfig = $this->getDepthConfig($depth);
        $skipTiles = &$featureConfig['skipTiles'];
        $breakOnSkip = &$featureConfig['breakOnSkip'];
        $connected = $featureConfig['connected'];
        $connectedRows = $featureConfig['connectedRows'];
        $i = $this->random->weighted(array_column($featureConfig['reels'], 'chance'));
        $config = $featureConfig['reels'][$i];
        if ($breakOnSkip && array_intersect($config['reels'],
                array_column(Calculator::get2dPositions($buffer, $skipTiles), 0))) {
            return null;
        }
        $reels = $this->pickReels($featureConfig, $config['reels']);
        if (!$reels) return null;
        $rows = $this->pickRows($buffer, $config, $reels);
        $positions = [];
        $tmpPositions = [];
        $reverseAll = $connectedRows ? $this->random->single($this->chanceReverse) : null;
        foreach ($reels as $x => $c) {
            for ($y = $rows[$x][0], $yMax = $rows[$x][1]; $y <= $yMax; $y++) {
                if (isset($skipPositions[$x . '-' . $y]) || in_array($buffer[$x][$y], $skipTiles, true)) {
                    continue;
                }
                if (!isset ($tmpPositions[$x])) {
                    $tmpPositions[$x] = [];
                }
                $tmpPositions[$x][] = [$x, $y];
            }
            if (array_key_exists($x,$tmpPositions)
                && ($reverseAll || ($reverseAll === null && $connected && $this->random->single($this->chanceReverse)))
            ) {
                $tmpPositions[$x] = array_reverse($tmpPositions[$x]);
            }
        }
        foreach ($reels as $reel => $count) {
            if (!isset ($tmpPositions[$reel])) continue;
            $pool = $connected ? $tmpPositions[$reel] : $this->random->shuffle($tmpPositions[$reel]);
            $c = 0;
            foreach ($pool as [$x, $y]) {
                $key = $x . '-' . $y;
                $positions[$key] = [$x, $y, $buffer[$x][$y]];
                $c ++;
                if ($c >= $count) break;
            }
        }
        return $positions ?: null;
    }
    protected function getDepthConfig($depth): array
    {
        $config = [
            'skipTiles'     => Calculator::getUpTo($this->skipTiles, $depth),
            'count'         => $this->count ? Calculator::getUpTo($this->count, $depth) : null,
            'sameCount'     => $this->sameCount ? Calculator::getUpTo($this->sameCount, $depth) : null,
            'reels'         => Calculator::getUpTo($this->reels, $depth),
            'retries'       => 0,
            'connected'     => null,
            'connectedRows' => null,
            'breakOnSkip'   => null
        ];
        if (!$config['skipTiles']) {
            $config['skipTiles'] = [];
        }
        if (!$config['count'] && !$config['sameCount']) {
            throw new GameException('Cannot find the right count config for depth: ' . $depth);
        }
        if (!$config['reels']) {
            throw new GameException('Cannot find the right reels config for depth: ' . $depth);
        }
        if (!empty($this->connected)) {
            $config['connected'] = Calculator::getUpTo($this->connected, $depth);
        }
        if (!empty($this->connectedRows)) {
            $config['connectedRows'] = Calculator::getUpTo($this->connectedRows, $depth);
        }
        if (!empty($this->breakOnSkip)) {
            $config['breakOnSkip'] = Calculator::getUpTo($this->breakOnSkip, $depth);
        }
        return $config;
    }
    protected function pickRows(array $screen, array $reelsConfig, array $reels): array
    {
        $starRow = 0;
        $endRow = count($screen[0]) - 1;
        $rows = array_fill(0, count($screen), [$starRow, $endRow]);
        if (isset($reelsConfig['rows'])) {
            $sameRow = $reelsConfig['sameRow'] ?? true;
            if ($sameRow) {
                [$starRow, $endRow] = $this->random->series($reelsConfig['rows']);
                $rows = array_fill(0, count($screen), [$starRow, $endRow]);
            } else {
                foreach ($reels as $x => $count) {
                    $rows[$x] = $this->random->series($reelsConfig['rows']);
                }
            }
        }
        return $rows;
    }
    protected function pickReels(array $featureConfig, array $availableReels): array
    {
        $reels = [];
        if ($this->sameCount && !$this->count) {
            $c = $this->random->weighted($featureConfig['sameCount']);
            foreach ($availableReels as $reel) {
                $reels[$reel] = $c;
            }
        } else {
            foreach ($availableReels as $reel) {
                $reels[$reel] = $this->random->weighted($featureConfig['count'][$reel]);
            }
        }
        return $reels;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\FeatureBuyInterface
 ******************************************************************************/

interface FeatureBuyInterface
{
    public function spinFeature(string $feature, string $stake, ?array $state=null): array;
    public function hasFeatureBuy(): bool;
    public function getFeatures(): array;
}


/*******************************************************************************
 * Class: Math\Games\Slot\RT7sLuck\Format\FatTileFormat
 ******************************************************************************/

class FatTileFormat implements ActionFormatInterface
{
    public function format(Result $result, string $name): array
    {
        $formattedData = [];
        foreach ($result->getData($name) as [$reel, $index, $tileId, $width, $height, $multiplier]) {
            $index -= $result->getBuffer()->getOffset();
            $formattedData[] = [
                'tileId' => $tileId,
                'reel' => $reel,
                'index' => $index,
                'width' => $width,
                'height' => $height,
                'multiplier' => $multiplier
            ];
        }
        return $formattedData;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Helpers\ResultHelper
 ******************************************************************************/

class ResultHelper {
    public static function exportFreeSpins(array &$freeSpins = null, array &$exports = [], $name = 'freeSpins') {
        if (!$freeSpins) return;
        foreach ($freeSpins as $spin) {
            $tmp = null;
            if (isset($spin['gameData'][$name])) {
                $tmp = $spin['gameData'][$name];
                unset($spin['gameData'][$name]);
            }
            $exports[] = $spin;
            if ($tmp) self::exportFreeSpins($tmp, $exports, $name);
        }
    }
    public static function fixResult(Result $result, $name = 'respin') {
        $freeSpins = $result->getData(ucfirst($name));
        if (!$freeSpins) return;
        $spins = [];
        self::exportFreeSpins($freeSpins, $spins, $name);
        $win = new Decimal();
        foreach ($spins as $k => $spin) {
            $tmpTotalWin = new Decimal($spins[$k]['win']['total']);
            if (isset($spins[$k]['win'][$name])) {
                $tmpTotalWin = $tmpTotalWin->sub($spins[$k]['win'][$name]);
                $spins[$k]['win']['total'] = (string) $tmpTotalWin;
                unset($spins[$k]['win'][$name]);
            }
            $win = $win->add($tmpTotalWin);
        }
        $result->resetData(ucfirst($name));
        $result->resetWin(ucfirst($name));
        $result->setData(ucfirst($name), $spins);
        $result->setWin(ucfirst($name), $win, true, false, true);
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Actions\FreeSpins
 ******************************************************************************/

class FreeSpins extends Action
{
    protected $name = 'FreeSpins';
    protected $spinsChance;
    protected $initialSpins;
    protected $spins = 0;
    protected $minTiles;
    protected $step;
    protected $current = 0;
    protected $depth = 0;
    protected $increment = 0;
    protected $totalSpins = 0;
    public static function getConfig(): array
    {
        return [
            'FreeSpins' => [
                'spins' => [
                    1000 => [
                        9 => 100
                    ]
                ],
                'minTiles' => [1000 => 3],
                'step' => [1000 => 3]
            ]
        ];
    }
    protected function setup(array $config)
    {
        $this->triggers['flatResult'] = true;
        $this->spinsChance = $config['spins'];
        $this->minTiles = $config['minTiles'] ?? [10000 => 0];
        $this->step     = $config['step'] ?? [10000 => 0];
    }
    public function handle(Result $result, string $active): ?Result {
        $this->setActive($active);
        return $this->operation(Result::copy($result));
    }
    protected function operation(Result $result): ?Result
    {
        $freeSpins = [];
        $totalWin = new Decimal();
        if ($this->depth <= 0) {
            $this->current    = 0;
            $this->totalSpins = 0;
        }
        $this->initialSpins = $spins = $this->calcSpinsCount($result);
        if ('FreeSpins' === $this->name && false === $this->isActive()) {
            $result->setData('InitialSpins', $spins);
        }
        $this->depth ++;
        $this->spins       = $spins;
        $this->totalSpins += $spins;
        $nextDepth = $result->getDepth() + 1;
        $nextResult = $result;
        for ($i = 0; $i < $spins; $i++) {
            $this->current ++;
            $this->increment  = 0;
            $nextResult->setDepth($nextDepth);
            $nextResult = $this->next->handle($nextResult, $this->activeConfiguration);
            if ($nextResult === null) {
                $this->current    = 0;
                $this->totalSpins = 0;
                $this->depth--;
                $result->setDepth($nextDepth - 1);
                return null;
            }
            $spins       += $this->increment;
            $this->spins += $this->increment;
            $this->totalSpins += $this->increment;
            $this->increment = 0;
            $freeSpins[] = $nextResult->build(false);
            $totalWin    = $totalWin->add($nextResult->calcTotalWin(true));
            $this->spins --;
        }
        $this->depth --;
        if ($this->depth <= 0) {
            $this->current    = 0;
            $this->totalSpins = 0;
        }
        $result->popState();
        $result->pushState($nextResult->popState());
        $result->setDepth($nextDepth - 1);
        $result->setWin($this->name , $totalWin, true, false, true);
        $result->setData($this->name, $freeSpins);
        if (true === $this->triggers['flatResult']) {
            ResultHelper::fixResult($result, lcfirst($this->name));
        }
        return $result;
    }
    public function increment(int $count): void
    {
        $this->increment += $count;
    }
    public function isActive() {
        return (bool) $this->depth;
    }
    public function isFirst() {
        return $this->current <= 1;
    }
    public function isLast() {
        return $this->spins === 1 && $this->increment === 0;
    }
    public function getSpins(): int
    {
        return $this->spins;
    }
    public function getCurrent(): int
    {
        return $this->current;
    }
    public function getTotalSpins(): int
    {
        return $this->totalSpins;
    }
    public function getInitialSpins(): int
    {
        return $this->initialSpins;
    }
    public function calcSpinsCount(Result $result): int
    {
        $depth        = $result->getDepth();
        $spins        = $this->random->weighted(Calculator::getUpTo($this->spinsChance, $depth));
        $symbolsCount = count($result->getPositions(false));
        $minTiles     = Calculator::getUpTo($this->minTiles, $depth);
        $step         = Calculator::getUpTo($this->step, $depth);
        if (0 < $step) $spins += ($symbolsCount - $minTiles) * $step;
        return $spins > 0 ? $spins : 0;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\Helpers\SlotHelper
 ******************************************************************************/

class SlotHelper {
    public static function getLastBuffer(Result $result): array
    {
        if ($respin = $result->getData('Respin')) {
            return self::getLastSpinBuffer(array_pop($respin), 'freeSpins');
        }
        if ($respin = $result->getData('FreeSpins')) {
            return self::getLastSpinBuffer(array_pop($respin), 'respin');
        }
        return $result->getBuffer()->build();
    }
    protected static function getLastSpinBuffer(array $respin, $name = 'respin'): array {
        if (isset($respin['gameData'][$name])) {
            $newName = $name === 'respin' ? 'freeSpins' : 'respin';
            return self::getLastSpinBuffer(array_pop($respin['gameData'][$name]), $newName);
        }
        return $respin['gameData']['reelsBuffer'];
    }
    public static function calcWin(array $lines): Decimal {
        $win = new Decimal();
        foreach ($lines as $line) {
            $win = $win->add($line->calcWinnings());
        }
        return $win;
    }
    public static function getLinesPositions(array $lines, array $buffer, int $offset): array
    {
        $positions = [];
        foreach ($lines as $line) {
            foreach ($line->getPositions() as [$x, $y]) {
                $y += $offset;
                $linePosKey = $x . '-' . $y;
                $positions[$linePosKey] = [$x, $y, $buffer[$x][$y]];
            }
        }
        return $positions;
    }
}


/*******************************************************************************
 * Class: Math\Games\Utils\Rules
 ******************************************************************************/

class Rules {
    public static function validateStake($stake, $allowZero = false) {
        $tmp = new Decimal($stake);
        $ref = new Decimal($tmp->mul(10)->toString(), 0);
        $tmp = $tmp->mul(10);
        if (!$tmp->sub($ref)->isEqual(0)){
            return false;
        }
        if($ref->isSmaller('0')){
            return false;
        }
        if(!$allowZero && $ref->isEqual('0')){
            return false;
        }
        return true;
    }
    public static function validateStakeDivisible($stake, $fold = '0.1') {
        $tmp = (new Decimal($stake))->div($fold);
        $int = new Decimal($tmp, 0);
        $stakeDec = new Decimal($stake);
        if (!$stakeDec->sub((new Decimal($fold))->mul($int))->isEqual(0)) {
            return false;
        }
        return true;
    }
    public static function validatePayout(Decimal $winMultiplier, $minPayout = null, $maxPayout = null): bool
    {
        $coverMinPayout = $minPayout === null || $winMultiplier->isLargerOrEqual($minPayout);
        $coverMaxPayout = $maxPayout === null || $winMultiplier->isSmallerOrEqual($maxPayout);
        return $coverMinPayout && $coverMaxPayout;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\SlotInterface
 ******************************************************************************/

interface  SlotInterface {
    public function getSettings(): array;
    public function spin($stake, array $state): array;
}


/*******************************************************************************
 * Class: Math\Games\Slot\SlotGame
 ******************************************************************************/

abstract class SlotGame implements SlotInterface, FeatureBuyInterface
{
    const JACKPOT_SYMBOL = 100;
    protected $rows = 1;
    protected $cols = 1;
    protected $offset = 1;
    protected $hasJackpot = false;
    protected $reelsChance;
    protected $config;
    protected $random;
    protected $lineCalculator;
    private $gameRules;
    public function __construct($rng=null, array $config = null, array $gameRules=[])
    {
        $this->random = new Chance($rng);
        $this->gameRules = $gameRules;
        $this->configure();
    }
    public function getRandom(): Chance
    {
        return $this->random;
    }
    public function getCols():int
    {
        return $this->cols;
    }
    public function getRows():int
    {
        return $this->rows;
    }
    public function getOffset():int
    {
        return $this->offset;
    }
    public function hasJackpot():bool
    {
        return $this->hasJackpot;
    }
    public function getConfig($transformPays = false):array
    {
        $config = array_merge([], $this->config);
        $config['lines']     = $this->config['payout']['lines'];
        $config['tiles']     = $this->config['payout']['tiles'];
        $config['direction'] = $this->config['payout']['direction'];
        if (!$transformPays) return $config;
        $tile = null;
        $linesCount = count($config['lines']);
        if ($config['tiles']) foreach ($config['tiles'] as $i => $tile) {
            $pay = null;
            foreach ($config['tiles'][$i]['pays'] as $p => $pay) {
                $config['tiles'][$i]['pays'][$p] = (new Decimal($config['tiles'][$i]['pays'][$p]))->mul($linesCount);
            }
        }
        unset($tile);
        if ($config['extraWin']) foreach ($config['extraWin'] as $i => $win) {
            $config['extraWin'][$i] = new Decimal($config['extraWin'][$i]);
        }
        return $config;
    }
    public function getSettings(): array
    {
        $json     = $this->getConfig(true);
        $paysType = $json['payout']['direction'];
        $keys = ['id', 'cols', 'rows', 'offset', 'tiles', 'lines', 'extraWin', 'multiplierSequence', 'multiplierList', 'pickerList', 'instantWinPays'];
        foreach ($json as $key => $value) {
            if (!in_array($key, $keys, true)) unset ($json[$key]);
        }
        $json['reelsBuffer'] = $this->createSettingsResult(new Decimal(1))->getBuffer()->build();
        $json['paysType']    = $paysType;
        $json['featureBuy'] = [];
        if ($this->hasFeatureBuy()) {
            foreach ($this->getFeatures() as $name => $feature) {
                $json['featureBuy'][] = [
                    'name'  => $name,
                    'price' => $feature['price']
                ];
            }
        }
        return [
            'gameData' => $json
        ];
    }
    public function calcLines(array $screen, array $wilds=[], array $multipliers=[]): array
    {
        return $this->lineCalculator->calcLines($screen, $wilds, $multipliers);
    }
    final public function spin($stake, ?array $state = null): array
    {
        if (!Rules::validateStake($stake)) {
            throw new GameException('Wrong stake is provided');
        }
        $result = $this->createResult(new Decimal($stake), $state);
        $result = $this->preSpinAction($result);
        $result = $this->execSpin($result);
        $result = $this->postSpinAction($result);
        if ($this->hasJackpot()) {
            return $this->execJackpotTiles($result)->build();
        }
        return $result->build();
    }
    final public function spinJackpot($stake, ?array $state = null): array
    {
        if (!$this->hasJackpot()) {
            throw new GameException('This game does not have jackpot!');
        }
        if (!Rules::validateStake($stake)) {
            throw new GameException('Wrong stake is provided');
        }
        $result = $this->createResult(new Decimal($stake), $state);
        $result = $this->preSpinAction($result);
        $result = $this->execSpin($result);
        $result = $this->postSpinAction($result);
        return $this->execJackpotTiles($result, true)->build();
    }
    final public function spinFeature(string $feature, string $stake, ?array $state=null): array
    {
        $gameFeatures  = $this->getFeatures();
        if (!Rules::validateStake($stake)) {
            throw new GameException('Wrong stake is provided');
        }
        if (!$this->hasFeatureBuy()) {
            throw new GameException('This game does not have enabled Feature Buy!');
        }
        if (!isset($gameFeatures[$feature]['config'])) {
            throw new GameException("Feature [$feature] does not exists!");
        }
        $featureConfig = $gameFeatures[$feature]['config'];
        $depth         = $featureConfig['depth'];
        $reelCategory  = $featureConfig['reelCategory'];
        $minPayout     = $featureConfig['payout']['min'];
        $maxPayout     = $featureConfig['payout']['max'];
        $result = $this->createResult(new Decimal($stake), $state, $depth, $reelCategory);
        $result->setMode($feature);
        $result = $this->preSpinFeatureAction($result);
        $result = $this->execFeature($result, $feature);
        $result = $this->postSpinFeatureAction($result);
        $totalWin = $result->calcTotalWin(true)->div($result->getStake());
        $result->setData('Bonus', [
            'name' => $feature,
            'trigger' => [
                (int) $totalWin->isLargerOrEqual($minPayout),
                (int) $totalWin->isSmallerOrEqual($maxPayout)
            ]
        ]);
        return $result->build();
    }
    final public function hasFeatureBuy(): bool
    {
        return $this->config['hasFeatureBuy'] ?? false;
    }
    final public function getFeatures(): array
    {
        return $this->hasFeatureBuy() ? $this->config['features']['FeatureBuy'] : [];
    }
    final public function getRule(string $name, $defaultValue=false)
    {
        return $this->gameRules[lcfirst($name)] ?? $defaultValue;
    }
    protected function configure():void
    {
        $this->rows   = $this->config['rows'];
        $this->cols   = $this->config['cols'];
        $this->offset = $this->config['offset'];
        $this->hasJackpot = isset($this->config['hasJackpot']) && $this->config['hasJackpot'];
        $this->reelsChance = [];
        foreach ($this->config['reels'] as $category => &$rr) {
            $this->reelsChance[$category] = array_column($this->config['reels'][$category], 'chance');
        }
        $this->lineCalculator = LineCalculator::create($this->config['payout']);
        $this->setup($this->config);
    }
    protected function getReelCategory(?string $reelCategory = null): string
    {
        return $reelCategory ?? 'default';
    }
    protected function createResult(Decimal $stake, ?array $state = null, int $depth=1, ?string $reelCategory = null): Result
    {
        $buffer = $this->createBuffer($reelCategory, $state);
        $result = Result::create($stake, $buffer, $depth);
        $result->pushState($state);
        return $result;
    }
    protected function createBuffer(?string $reelCategory, ?array $state): Buffer
    {
        $reelCategory = $this->getReelCategory($reelCategory);
        $reelIndex = $this->getReelIndex($reelCategory);
        $stopPositions = $this->createStopPositions($reelCategory, $reelIndex, $state);
        $reels = $this->spinReels($reelCategory, $reelIndex, $stopPositions);
        return Buffer::create($this->rows, $this->offset, $reels, $reelCategory, $reelIndex, $stopPositions);
    }
    protected function createStopPositions(string $category, int $index, ?array $state)
    {
        $stops = [];
        $reelConfig = &$this->config['reels'][$category][$index];
        for ($x=0; $x < $this->cols; $x++) {
            $stops[] = $this->random->random(0, count($reelConfig['tiles'][$x]) - 1);
        }
        return $stops;
    }
    protected function spinReels(string $category, int $index, array $stops, ?array $state = null): array
    {
        $reels = []; 
        $bufferSize = $this->rows + 2 * $this->offset; 
        $reelConfig = &$this->config['reels'][$category][$index]; 
        foreach ($stops as $x => $stopPosition) {
            $reelSize = count($reelConfig['tiles'][$x]); 
            $reels[$x] = []; 
            for ($y = $bufferSize - 1; $y >= 0; $y--) {
                array_unshift($reels[$x], $reelConfig['tiles'][$x][$stopPosition]);
                $stopPosition--;
                if (0 > $stopPosition) {
                    $stopPosition += $reelSize;
                }
            }
        }
        return $reels;
    }
    protected function createSettingsResult(Decimal $stake, int $retries=20): Result
    {
        while (0 < $retries--) {
            $result = $this->createResult($stake, null, 1, 'settings');
            $lines  = $this->calcLines($result->getBuffer()->getScreen());
            $result->setLines($lines);
            if ($result->calcTotalWin(true)->isEqual(0)) {
                return $result;
            }
        }
        throw new GameException('Cannot create Result.');
    }
    protected function execJackpotTiles(Result $result, bool $isJackpotWin = false): Result
    {
        if ($isJackpotWin) {
            $this->placeJackpotTilesWin($result);
        }
        if (!$isJackpotWin && $this->canPlaceJackpotTiles($result)) {
            $this->placeJackpotTiles($result);
        }
        return $result;
    }
    protected function placeJackpotTilesWin(Result $result): void
    {
        $jackpotConfig    = $this->config['features']['JackpotTiles'];
        $reelsConfig      = Calculator::getUpTo($jackpotConfig['reels'], 1000);
        $reelChances      = array_column($reelsConfig, 'chance');
        $reelsChanceIndex = $this->random->weighted($reelChances);
        $buffer           = SlotHelper::getLastBuffer($result);
        $buffer           = array_column($buffer, 1);
        $placeTilesOnScreen = $this->placeJackpotTilesOnScreen($result);
        $skipPositions      = $result->getPositions();
        $jackpotTiles = [];
        foreach ($reelsConfig[$reelsChanceIndex]['reels'] as $reelIndex => $reel) {
            $allIndexes = [];
            $indexes    = [];
            foreach ($buffer[$reel] as $index => $tile) {
                if ($tile === 0) {
                    continue;
                }
                $allIndexes[] = $index;
                $isSkip =
                    in_array($tile, $jackpotConfig['skip'], true) ||
                    isset($skipPositions[$reel.'-'. ($index + $this->offset)]);
                if ($isSkip) {
                    continue;
                }
                $indexes[] = $index;
            }
            $onScreen = $placeTilesOnScreen;
            if ($indexes) {
                $index = $this->random->series($indexes);
                $onScreen = $onScreen && $reelsConfig[$reelsChanceIndex]['active'][$reelIndex] === 1;
            }
            else {
                $onScreen = false;
                $index = $this->random->series($allIndexes);
            }
            $jackpotTiles[] = [
                'reel'   => $reel,
                'index'  => $index,
                'screen' => $onScreen
            ];
            if ($onScreen) $result->getBuffer()->setTileToScreen($reel, $index, self::JACKPOT_SYMBOL);
        }
        $result->setData('JackpotTiles', $jackpotTiles);
    }
    protected function placeJackpotTiles(Result $result): void
    {
        $jackpotConfig    = $this->config['features']['JackpotTiles'];
        $reelsConfig      = Calculator::getUpTo($jackpotConfig['reels'], 1);
        $reelChances      = array_column($reelsConfig, 'chance');
        $reelsChanceIndex = $this->random->weighted($reelChances);
        $placeOnScreen    = $this->random->single($jackpotConfig['chance']);
        $skipPositions    = $result->getPositions();
        if ($placeOnScreen || $this->random->single($jackpotConfig['chanceBuffer'])) {
            $bufferScreen = $result->getBuffer()->getScreen();
            $buffer = $result->getBuffer()->getReels();
            foreach ($reelsConfig[$reelsChanceIndex]['reels'] as $reelIndex => $reel) {
                $indexes    = [];
                if ($placeOnScreen) {
                    foreach ($bufferScreen[$reel] as $index => $tile) {
                        $isSkip = in_array($tile, $jackpotConfig['skip'], true) || isset($skipPositions[$reel.'-'. ($index + $this->offset)]);
                        if ($isSkip) continue;
                        $indexes[] = $index;
                    }
                    if ($indexes) {
                        $index = $this->random->series($indexes);
                        $result->getBuffer()->setTileToScreen($reel, $index, self::JACKPOT_SYMBOL);
                    }
                }
                else {
                    $indexes    = [];
                    foreach ($buffer[$reel] as $index => $tile) {
                        if (in_array($tile, $jackpotConfig['skip'], true)) continue;
                        if ($index >= $this->offset) break;
                        $indexes[] = $index;
                    }
                    if ($indexes) {
                        $index = $this->random->series($indexes);
                        $result->getBuffer()->setTile($reel, $index, self::JACKPOT_SYMBOL);
                    }
                }
            }
        }
    }
    protected function placeJackpotTilesOnScreen(Result $result): bool
    {
        return $result->calcTotalWin(true)->isSmallerOrEqual(0);
    }
    protected function canPlaceJackpotTiles(Result $result): bool
    {
        return
            $result->calcTotalWin(true)->isSmallerOrEqual(0) &&
            $result->getData('Respin') === null &&
            $result->getData('FreeSpins') === null;
    }
    protected function preSpinAction(Result $result): Result
    {
        return $result;
    }
    protected function postSpinAction(Result $result): Result
    {
        return $result;
    }
    protected function preSpinFeatureAction(Result $result): Result
    {
        return $this->preSpinAction($result);
    }
    protected function postSpinFeatureAction(Result $result): Result
    {
        return $this->postSpinAction($result);
    }
    abstract protected function setup(array $config): void;
    abstract protected function execSpin(Result $result): Result;
    protected function execFeature(Result $result, string $feature): Result {
        throw new GameException('Not implemented!');
    }
    protected function getReelIndex(?string $reelCategory)
    {
        return $this->random->weighted($this->reelsChance[$reelCategory]);
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\FreeSpinGame
 ******************************************************************************/

abstract class FreeSpinGame extends SlotGame implements ActionInterface
{
    final public function setNext(ActionInterface $nextAction, string $name): ActionInterface {
        throw new GameException('FreeSpins Game does not have next action');
    }
    final public function setActive(string $active): ActionInterface
    {
        return $this;
    }
    final public function handle(Result $result, string $active): ?Result
    {
        $spinResult  = $this->createResult($result->getStake(), $result->popState(), $result->getDepth());
        $spinResult->setMode($active);
        $spinResult = $this->preSpinAction($spinResult);
        $result = $this->execFreeSpin($spinResult);
        if ($result !== null) {
            $result = $this->postSpinAction($result);
        }
        return $result;
    }
    abstract protected function execFreeSpin(Result $result): ?Result;
}


/*******************************************************************************
 * Class: Math\Games\Slot\RT7sLuck\Format\LockedTilesFormat
 ******************************************************************************/

class LockedTilesFormat implements ActionFormatInterface
{
    public function format(Result $result, string $name): array
    {
        $data = [];
        [$lockedTiles, $isLast] = $result->getData($name);
        foreach ($lockedTiles as [$x, $y, $tileId, $cashValue]) {
            $y -= $result->getBuffer()->getOffset();
            if ($y >= 0 && $y < $result->getBuffer()->getRows()) {
                $data[] = [
                    'reel' => $x,
                    'index' => $y,
                    'tileId' => $tileId,
                    'spins' => $isLast ? 0 : 999,
                    'amount' => $result->getStake()->mul($cashValue),
                    'multiplier' => $cashValue
                ];
            }
        }
        return $data;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\RT7sLuck\Format\ScattersFormat
 ******************************************************************************/

class ScattersFormat implements ActionFormatInterface
{
    public function format(Result $result, string $name): array
    {
        $data = [];
        $scatters = $result->getData($name);
        foreach ($scatters as $k => [$x, $y, $tileId, $cashValue]) {
            $y -= $result->getBuffer()->getOffset();
            if ($y >= 0 && $y < $result->getBuffer()->getRows()) {
                $data[] = [
                    'reel' => $x,
                    'index' => $y,
                    'tileId' => $tileId,
                    'amount' => $result->getStake()->mul($cashValue),
                    'multiplier' => $cashValue
                ];
            }
        }
        return $data;
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\RT7sLuck\RT7sLuck
 ******************************************************************************/

class RT7sLuck extends FreeSpinGame implements FeatureBuyInterface
{
    protected $custom;
    private $freeSpinsRef;
    private $wilds;
    private $scatters;
    protected function setup(array $config): void
    {
        $this->custom = $config['features']['Custom'];
        $this->freeSpinsRef = new FreeSpins($this->random, $config['features']['FreeSpins']);
        $this->freeSpinsRef
            ->setNext($this, 'FreeSpins');
        $features = $config['features'];
        $chance = new FeatureChance($this->random, $features['FeatureChance']);
        $randomTiles = new RandomTiles($this->random, $features['RandomTiles']);
        $randomReelPositions = new RandomPositions($this->random, $features['ReelPositions']);
        $randomReelPositions->setStrategy(new ReelPositionsStrategy($this->random));
        $this->wilds = $chance;
        $this->wilds
            ->setNext($randomReelPositions, 'RandomWilds')
            ->setNext($randomTiles, 'RandomWilds');
        $this->scatters = $chance;
        $this->scatters
            ->setNext($randomReelPositions, 'Scatters')
            ->setNext($randomTiles, 'Scatters');
    }
    protected function preSpinAction(Result $result): Result
    {
        $state = $result->popState();
        if (empty($state)) {
            $state = $this->custom['GameState'];
        }
        if (isset($this->custom['Depth'][$result->getMode()])) {
            $result->setDepth($this->custom['Depth'][$result->getMode()]);
        }
        if ($result->getMode() === 'FreeSpins') {
            $result->setBuffer($this->spinReelsRespin($result));
        }
        $result->pushState($state);
        return $result;
    }
    protected function preSpinFeatureAction(Result $result): Result
    {
        $initialScreen = $result->getBuffer()->getScreen();
        $wildPositions = [];
        $scattersPositions = [];
        if ($featureResult = $this->wilds->handle($result, 'RandomWilds')) {
            $wildPositions = $featureResult->getPositions();
            if ($result->getDepth() === 1000) {
                $result = $featureResult;
            }
        }
        if ($featureResult = $this->scatters->handle($result, 'Scatters')) {
            $scattersPositions = $featureResult->getPositions();
            if ($result->getDepth() === 1000) {
                $result = $featureResult;
            }
        }
        if ($result->getDepth() === 2000) {
            foreach ($scattersPositions as $scattersPosition) {
                unset($wildPositions[$scattersPosition[0] . '-3']);
                unset($wildPositions[$scattersPosition[0] . '-4']);
                unset($wildPositions[$scattersPosition[0] . '-5']);
            }
            $result->getBuffer()->setTiles($scattersPositions);
            $reelPositions = [];
            foreach ($wildPositions as $key => $wildPosition) {
                $reelPositions[$wildPosition[0]][] = $wildPosition;
            }
            foreach ($reelPositions as $reel => $positionsOnReel) {
                if (count($positionsOnReel) === 1) {
                    if ($positionsOnReel[0][1] === 4) {
                        $newIndex = !$this->random->random(0, 1) ? 3 : 5;
                        $wildPositions[$reel . '-' . 4][1] = $newIndex;
                    }
                } elseif (count($positionsOnReel) === 2) {
                    if (($positionsOnReel[0][1] === 3 && $positionsOnReel[1][1] === 5) ||
                        ($positionsOnReel[1][1] === 3 && $positionsOnReel[0][1] === 5)) {
                        if (!$this->random->random(0, 1)) {
                            $wildPositions[$reel . '-' . 5][1] = 4;
                        } else {
                            $wildPositions[$reel . '-' . 3][1] = 4;
                        }
                    }
                }
                foreach ($wildPositions as $wildPosition) {
                    if ($wildPosition[0] === $reel) {
                        $result->getBuffer()->setTile($wildPosition[0], $wildPosition[1], $wildPosition[2]);
                    }
                }
            }
        }
        return $this->preSpinAction($result);
    }
    protected function postSpinFeatureAction(Result $result): Result
    {
        return $this->postSpinAction($result);
    }
    protected function postSpinAction(Result $result): Result
    {
        if ($result->getMode() === 'FreeSpins') {
            $result->setMode('Respin');
        }
        if ($result->getData('FreeSpins') !== null) {
            $result->setWin("Respin", $result->getWin("FreeSpins"));
            $result->resetWin("FreeSpins");
            $result->setData("Respin", $result->getData('FreeSpins'));
            $result->resetData('FreeSpins');
        }
        $result->setDataFormat('Scatters', new ScattersFormat());
        $result->setDataFormat('LockedTiles', new LockedTilesFormat());
        $result->setDataFormat('FatTiles', new FatTileFormat());
        return $result;
    }
    protected function execSpin(Result $result): Result
    {
        $fullReelsWilds = [];
        for ($reel = 0; $reel < $this->cols; $reel++) {
            $notWild = false;
            for ($index = 0; $index < $this->rows; $index++) {
                if ($result->getBuffer()->getScreen()[$reel][$index] !== 7) {
                    $notWild = true;
                    break;
                }
            }
            if (!$notWild) {
                $fullReelsWilds[] = $reel;
            }
        }
        $lines = $this->calcLines($result->getBuffer()->getScreen());
        if ($lines && $fullReelsWilds) {
            $mul = 1;
            $fatTiles = [];
            $components = [];
            foreach ($fullReelsWilds as $fullReel) {
                $fatTiles[$fullReel . '-' . ($this->offset)] = [$fullReel, $this->offset, 7, 1, $this->rows, $this->custom['fatTileMultiplier']];
                $mul *= 2;
                $components[] = 2;
            }
            foreach ($lines as $line) {
                $line->setMultiplier($mul, $components);
            }
            $result->setData("FatTiles", $fatTiles);
        }
        $result->setLines($lines);
        $scatters = $result->getBuffer()->filterPositions($result->getBuffer()->findTiles(8), Buffer::SCREEN);
        $scattersNew = [];
        $state = $result->popState();
        foreach ($scatters as $scatter) {
            $scatterValue = $this->random->weighted($this->custom['cashValues']);
            $scattersNew[$scatter[0] . '-' . $scatter[1]] = [$scatter[0], $scatter[1], $scatter[2], $scatterValue];
            if (isset($state['instantWin'])) {
                $state['instantWin'] += $scatterValue;
            } else {
                $state['instantWin'] = $scatterValue;
            }
        }
        $result->pushState($state);
        $result->setData("Scatters", $scattersNew);
        if (count($scattersNew) >= 3) {
            $state = $result->popState();
            $state['progress'] = 0;
            $state['locked'] = $scattersNew;
            $result->pushState($state);
            if ($featureResult = $this->freeSpinsRef->handle($result, 'FreeSpins')) {
                $result = $featureResult;
            }
        }
        $state = $result->popState();
        $result->pushState($state);
        $result->popState();
        return $result;
    }
    protected function execFreeSpin(Result $result): ?Result
    {
        $state = $result->popState();
        $locked = $state['locked'];
        if (!empty($locked)) {
            foreach ($state['locked'] as $lockedTile) {
                $result->getBuffer()->setTile($lockedTile[0], $lockedTile[1], 9);
            }
        }
        $scatters = array_diff_key($result->getBuffer()->filterPositions($result->getBuffer()->findTiles(8), Buffer::SCREEN), $locked);
        $scattersNew = [];
        foreach ($scatters as $scatter) {
            $scatterValue = $this->random->weighted($this->custom['cashValues']);
            $scattersNew[$scatter[0] . '-' . $scatter[1]] = [$scatter[0], $scatter[1], $scatter[2], $scatterValue];
            if (isset($state['instantWin'])) {
                $state['instantWin'] += $scatterValue;
            } else {
                $state['instantWin'] = $scatterValue;
            }
        }
        $result->setData("Scatters", $scattersNew);
        if (!empty($scatters)) {
            $this->freeSpinsRef->increment(3 - (2 - $state['progress']));
            $state['locked'] = array_merge($locked, $scattersNew);
            $result->setData("Progress", ["current" => $state['progress'], 'next' => 0, 'target' => 3]);
            $state['progress'] = 0;
        } else {
            $result->setData("Progress", ["current" => $state['progress'], 'next' => $state['progress'] + 1, 'target' => 3]);
            $state['progress'] += 1;
        }
        $result->setData("LockedTiles", [$state['locked'], false]);
        if ($state['progress'] === 3 || count($state['locked']) === ($this->cols * $this->rows)) {
            $this->freeSpinsRef->increment(-999);
            $mul = $state['instantWin'];
            $options = [];
            if (isset($this->config['multiplierSequence']['scatters'][count($state['locked'])])) {
                $prize = $this->config['multiplierSequence']['scatters'][count($state['locked'])];
                $mul += $prize;
                $options[] = ['amount' => $result->getStake()->mul($prize), 'multiplier' => $prize, 'type' => 'topPrize'];
            }
            $result->setData("LockedTiles", [$state['locked'], true]);
            foreach ($state['locked'] as $lockedTile) {
                $options[] = ['amount' => $result->getStake()->mul($lockedTile[3]), 'multiplier' => $lockedTile[3], 'type' => 'cashValue'];
            }
            $result->setData('InstantWin', ['amount' => (new Decimal($mul))->mul($result->getStake()), 'multiplier' => $mul, 'options' => $options]);
            $result->setWin('InstantWin', (new Decimal($mul))->mul($result->getStake()));
        } else {
            $result->setData("LockedTiles", [$state['locked'], false]);
        }
        $result->pushState($state);
        $lines = $this->calcLines($result->getBuffer()->getScreen());
        $result->setLines($lines);
        return $result;
    }
    protected function placeJackpotTilesOnScreen(Result $result): bool
    {
        return $this->canPlaceJackpotTiles($result);
    }
    protected function canPlaceJackpotTiles(Result $result): bool
    {
        return $result->calcTotalWin(true)->isSmallerOrEqual(0)
            && $result->getData('SwapTiles') === null
            && $result->getData('FreeSpins') === null
            && $result->getData('Activator') === null
            && empty($result->getData('Scatters'))
            && $result->getData('FatTiles') === null;
    }
    protected function createResult(Decimal $stake, ?array $state = null, int $depth = 1, ?string $reelCategory = null): Result
    {
        if ($reelCategory === null) {
            if (!$this->freeSpinsRef->isActive()) {
                $reelCategory = 'default';
            } else {
                $reelCategory = 'freeSpins';
            }
        }
        $buffer = $this->createBuffer($reelCategory, $state);
        $result = Result::create($stake, $buffer, $depth);
        $result->pushState($state);
        return $result;
    }
    protected function getReelCategory(?string $reelCategory = null): string
    {
        if ($reelCategory !== null) {
            return $reelCategory;
        } elseif ($this->freeSpinsRef->isActive()) {
            return 'freeSpins';
        } else {
            return 'default';
        }
    }
    private function spinReelsRespin(Result $result): Buffer
    {
        for ($reel = 0; $reel < $this->cols; $reel++) {
            for ($index = 0; $index < $this->rows; $index++) {
                if ($this->random->single($this->custom['holdNRespinScatterChance'])) {
                    $result->getBuffer()->setTile($reel, $index + $this->offset, 8);
                }
            }
        }
        return $result->getBuffer();
    }
    protected function execFeature(Result $result, string $feature): Result
    {
        return $this->execSpin($result);
    }
}


/*******************************************************************************
 * Class: Math\Games\Slot\RT7sLuck\RT7sLuck_rtp96
 ******************************************************************************/

class RT7sLuck_rtp96 extends RT7sLuck
{
    protected $config = [
        'cols' => 5,
        'rows' => 3,
        'offset' => 3,
        'hasJackpot' => false,
        'payout' => [
            'direction' => LineType::LTR,
            'tiles' => [
                ['id' => 1, 'type' => Tile::TYPE_NORMAL, 'pays' => ['0', '0', '0.5', '1.0', '5.0']],
                ['id' => 2, 'type' => Tile::TYPE_NORMAL, 'pays' => ['0', '0', '0.5', '1.0', '5.0']],
                ['id' => 3, 'type' => Tile::TYPE_NORMAL, 'pays' => ['0', '0', '0.5', '1.0', '5.0']],
                ['id' => 4, 'type' => Tile::TYPE_NORMAL, 'pays' => ['0', '0', '0.7', '1.5', '10.0']],
                ['id' => 5, 'type' => Tile::TYPE_NORMAL, 'pays' => ['0', '0', '1.0', '2.5', '15.0']],
                ['id' => 6, 'type' => Tile::TYPE_NORMAL, 'pays' => ['0', '0', '2.5', '5.0', '50.0']],
                ['id' => 7, 'type' => Tile::TYPE_WILD, 'pays' => ['0', '0', '0', '0', '0']],
                ['id' => 8, 'type' => Tile::TYPE_SCATTER, 'pays' => ['0', '0', '0', '0', '0']],
                ['id' => 9, 'type' => Tile::TYPE_SCATTER, 'pays' => ['0', '0', '0', '0', '0']],
            ],
            'lines' => [
                [1, 1, 1, 1, 1],
                [0, 0, 0, 0, 0],
                [2, 2, 2, 2, 2],
                [0, 1, 2, 1, 0],
                [2, 1, 0, 1, 2],
                [0, 0, 1, 0, 0],
                [2, 2, 1, 2, 2],
                [1, 2, 2, 2, 1],
                [1, 0, 0, 0, 1],
                [1, 0, 1, 0, 1],
                [1, 2, 1, 2, 1],
                [0, 1, 0, 1, 0],
                [2, 1, 2, 1, 2],
                [1, 1, 0, 1, 1],
                [1, 1, 2, 1, 1],
                [0, 1, 1, 1, 0],
                [2, 1, 1, 1, 2],
                [0, 1, 2, 2, 2],
                [2, 1, 0, 0, 0],
                [0, 2, 0, 2, 0],
            ]
        ],
        'reels' => [
            'settings' => [
                [
                    'chance' => 100,
                    'tiles' => [
                        [6, 4, 6, 4, 6, 4, 6, 6, 6, 4, 4, 6, 6, 6, 4, 4, 4, 4, 6, 6, 6, 6, 6, 4, 6, 6, 6, 4, 4, 4, 4, 4, 4, 6, 4, 6, 6, 4, 4, 4],
                        [5, 3, 1, 2, 3, 2, 5, 1, 1, 5, 3, 3, 1, 5, 5, 2, 3, 1, 2, 1, 5, 5, 2, 5, 3, 5, 2, 1, 2, 5, 2, 3, 3, 1, 2, 1, 1, 5, 1, 1, 5, 5, 3, 2, 2, 3, 5, 1, 1, 3, 2, 3, 1, 3, 5, 5, 3, 2, 2, 3, 1, 2, 2, 3],
                        [5, 6, 5, 3, 3, 6, 2, 2, 5, 3, 6, 6, 5, 5, 2, 4, 2, 1, 5, 6, 1, 1, 5, 2, 1, 2, 6, 4, 4, 2, 3, 6, 1, 2, 4, 1, 6, 1, 4, 4, 4, 5, 6, 1, 3, 4, 3, 3, 2, 4, 5, 3, 3, 1],
                        [1, 5, 4, 1, 5, 4, 3, 6, 2, 6, 4, 4, 2, 1, 6, 3, 6, 1, 1, 5, 4, 4, 4, 6, 3, 3, 2, 1, 2, 6, 2, 5, 2, 2, 2, 3, 3, 6, 1, 3, 3, 5, 5, 1, 5, 6, 4, 5, 2, 6, 5, 4, 1, 3],
                        [1, 2, 6, 5, 6, 1, 1, 6, 6, 2, 5, 6, 3, 4, 4, 1, 5, 1, 1, 3, 2, 3, 2, 2, 3, 2, 2, 5, 2, 3, 5, 4, 4, 6, 1, 4, 5, 4, 1, 3, 4, 3, 4, 4, 6, 3, 5, 2, 6, 1, 5, 5, 3, 6]
                    ]
                ]
            ],
            'default' => [
                [
                    'chance' => 100,
                    'tiles' => [
                        [5, 5, 5, 2, 2, 2, 4, 4, 4, 8, 1, 1, 1, 3, 3, 3, 8, 4, 4, 4, 6, 6, 6, 1, 1, 1, 3, 3, 3, 4, 4, 4, 2, 2, 2, 3, 3, 3, 4, 4, 4, 6, 6, 6, 1, 1, 1, 2, 2, 2, 5, 5, 5, 1, 1, 1, 3, 3, 3, 2, 2, 2, 1, 1, 1, 5, 5, 5, 2, 2, 2, 3, 3, 3,],
                        [7, 7, 7, 4, 4, 4, 1, 1, 1, 2, 2, 2, 8, 5, 5, 5, 1, 1, 1, 8, 2, 2, 2, 3, 3, 3, 1, 1, 1, 2, 2, 2, 5, 5, 5, 3, 3, 3, 2, 2, 2, 4, 4, 4, 3, 3, 3, 2, 2, 2, 6, 6, 6, 3, 3, 3, 4, 4, 4, 6, 6, 6, 1, 1, 1, 4, 4, 4, 3, 3, 3, 1, 1, 1, 5, 5, 5,],
                        [7, 7, 7, 3, 3, 3, 1, 1, 1, 2, 2, 2, 8, 3, 3, 3, 1, 1, 1, 8, 4, 4, 4, 3, 3, 3, 1, 1, 1, 5, 5, 5, 2, 2, 2, 1, 1, 1, 6, 6, 6, 2, 2, 2, 4, 4, 4, 5, 5, 5, 3, 3, 3, 4, 4, 4, 6, 6, 6, 2, 2, 2, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 2, 2, 2, 7, 7, 7, 3, 3, 3, 1, 1, 1, 2, 2, 2, 8, 3, 3, 3, 1, 1, 1, 8, 4, 4, 4, 3, 3, 3, 1, 1, 1, 5, 5, 5, 2, 2, 2, 1, 1, 1, 6, 6, 6, 2, 2, 2, 4, 4, 4, 5, 5, 5, 3, 3, 3, 4, 4, 4, 6, 6, 6, 2, 2, 2, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 2, 2, 2, 3, 3, 3, 1, 1, 1, 2, 2, 2, 8, 3, 3, 3, 1, 1, 1, 8, 4, 4, 4, 3, 3, 3, 1, 1, 1, 5, 5, 5, 2, 2, 2, 1, 1, 1, 6, 6, 6, 2, 2, 2, 4, 4, 4, 5, 5, 5, 3, 3, 3, 4, 4, 4, 6, 6, 6, 2, 2, 2, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 2, 2, 2,],
                        [7, 7, 7, 3, 3, 3, 2, 2, 2, 1, 1, 1, 8, 5, 5, 5, 6, 6, 6, 8, 2, 2, 2, 1, 1, 1, 6, 6, 6, 3, 3, 3, 1, 1, 1, 2, 2, 2, 4, 4, 4, 3, 3, 3, 1, 1, 1, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 4, 4, 4, 2, 2, 2, 3, 3, 3, 4, 4, 4, 2, 2, 2, 5, 5, 5, 7, 7, 7, 3, 3, 3, 2, 2, 2, 1, 1, 1, 8, 5, 5, 5, 6, 6, 6, 8, 2, 2, 2, 1, 1, 1, 6, 6, 6, 3, 3, 3, 1, 1, 1, 2, 2, 2, 4, 4, 4, 3, 3, 3, 1, 1, 1, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 4, 4, 4, 2, 2, 2, 3, 3, 3, 4, 4, 4, 2, 2, 2, 5, 5, 5, 7, 7, 7, 3, 3, 3, 2, 2, 2, 1, 1, 1, 8, 5, 5, 5, 6, 6, 6, 8, 2, 2, 2, 1, 1, 1, 6, 6, 6, 3, 3, 3, 1, 1, 1, 2, 2, 2, 4, 4, 4, 3, 3, 3, 1, 1, 1, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 4, 4, 4, 2, 2, 2, 3, 3, 3, 4, 4, 4, 2, 2, 2, 5, 5, 5, 3, 3, 3, 2, 2, 2, 1, 1, 1, 8, 5, 5, 5, 6, 6, 6, 2, 2, 2, 1, 1, 1, 6, 6, 6, 3, 3, 3, 1, 1, 1, 2, 2, 2, 4, 4, 4, 3, 3, 3, 1, 1, 1, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 4, 4, 4, 2, 2, 2, 3, 3, 3, 4, 4, 4, 2, 2, 2, 5, 5, 5, 3, 3, 3, 2, 2, 2, 1, 1, 1, 8, 5, 5, 5, 6, 6, 6, 2, 2, 2, 1, 1, 1, 6, 6, 6, 3, 3, 3, 1, 1, 1, 2, 2, 2, 4, 4, 4, 3, 3, 3, 1, 1, 1, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 4, 4, 4, 2, 2, 2, 3, 3, 3, 4, 4, 4, 2, 2, 2, 5, 5, 5, 3, 3, 3, 2, 2, 2, 1, 1, 1, 8, 5, 5, 5, 6, 6, 6, 2, 2, 2, 1, 1, 1, 6, 6, 6, 3, 3, 3, 1, 1, 1, 2, 2, 2, 4, 4, 4, 3, 3, 3, 1, 1, 1, 4, 4, 4, 3, 3, 3, 5, 5, 5, 1, 1, 1, 4, 4, 4, 2, 2, 2, 3, 3, 3, 4, 4, 4, 2, 2, 2, 5, 5, 5,],
                        [1, 1, 1, 2, 2, 2, 3, 3, 3, 8, 1, 1, 1, 2, 2, 2, 8, 5, 5, 5, 6, 6, 6, 4, 4, 4, 5, 5, 5, 2, 2, 2, 4, 4, 4, 1, 1, 1, 3, 3, 3, 2, 2, 2, 1, 1, 1, 3, 3, 3, 2, 2, 2, 4, 4, 4, 5, 5, 5, 3, 3, 3, 4, 4, 4, 1, 1, 1, 6, 6, 6, 3, 3, 3,]
                    ]
                ]
            ],
            'freeSpins' => [
                [
                    'chance' => 100,
                    'tiles' => [
                        [9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9],
                        [9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9],
                        [9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9],
                        [9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9],
                        [9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9, 9],
                    ]
                ]
            ]
        ],
        'simulation' => [
            'features' => [
                'InstantWin' => ['type' => 'Object', 'winType' => 'specialWin'],
            ]
        ],
        'extraWin' => [
            'bigWin' => 15,
            'superWin' => 50,
            'megaWin' => 100
        ],
        'multiplierSequence' => [
            'scatters' => [
                8 => 10,
                9 => 20,
                10 => 30,
                11 => 50,
                12 => 100,
                13 => 200,
                14 => 1000,
                15 => 10000,
            ],
            'emptyId' => 9
        ],
        'features' => [
            'FreeSpins' => [
                'FreeSpins' => [
                    'spins' => [1000 => [3 => 100]],
                    'minTiles' => [1000 => 3],
                    'step' => [1000 => 0]
                ],
            ],
            'FeatureChance' => [
            ],
            'RandomPositions' => [
            ],
            'ReelPositions' => [
            ],
            'RandomTiles' => [
            ],
            'Custom' => [
                'GameState' => [],
                'ReelCategory' => [
                    'Normal' => 'default',
                    'FreeSpins' => 'freeSpins',
                ],
                'Depth' => [
                    'Normal' => 1,
                    'FreeSpins' => 100,
                ],
                'holdNRespinScatterChance' => 3.39,
                'fatTileMultiplier' => 2,
                'cashValues' => [
                    2 => 40,
                    3 => 35,
                    5 => 15,
                    8 => 7,
                    10 => 3
                ]
            ]
        ]
    ];
}
