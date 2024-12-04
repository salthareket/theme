<?php
/**
 * @link http://stackoverflow.com/questions/13129336/split-a-time-range-into-pieces-by-other-time-ranges
 * @link https://gist.github.com/gists/3977645
 * @author hakre
 */

class Range
{
    /**
     * @var DateTime
     */
    protected $start;

    /**
     * @var DateTime
     */
    protected $end;

    public function __construct(DateTime $start, DateTime $end) {
        $this->setStart($start);
        $this->setEnd($end);
    }

    /**
     * @return DateTime
     */
    public function getStart(): DateTime {
        return $this->start;
    }

    public function setStart(DateTime $start): void {
        $this->start = $start;
    }

    /**
     * @return DateTime
     */
    public function getEnd(): DateTime {
        return $this->end;
    }

    /**
     * @param DateTime $end
     * @throws InvalidArgumentException
     */
    public function setEnd(DateTime $end): void {
        if ($end < $this->start) {
            throw new InvalidArgumentException('End date cannot be before start date.');
        }
        $this->end = $end;
    }

    public function hasTime(DateTime $time): bool {
        return $this->start <= $time && $this->end >= $time;
    }

    public function hasContact(Range $range): bool {
        return $this->hasTime($range->start) || $this->hasTime($range->end);
    }

    public function isSame(Range $range): bool {
        return $this->start == $range->start && $this->end == $range->end;
    }

    public function isWithin(Range $range): bool {
        return $range->start > $this->start && $range->end < $this->end;
    }

    public function isSubset(Range $range): bool {
        return $range->hasTime($this->start) && $range->hasTime($this->end);
    }

    public function add(Range $range): void {
        if (!$this->hasContact($range)) {
            throw new InvalidArgumentException('Range needs to overlap.');
        }

        if ($range->start < $this->start) {
            $this->start = $range->start;
        }

        if ($range->end > $this->end) {
            $this->end = $range->end;
        }
    }

    public function subtract(Range $range): void {
        if ($this->isWithin($range)) {
            throw new InvalidArgumentException('Range would divide.');
        }

        if ($this->isSubset($range)) {
            throw new InvalidArgumentException('Range would delete.');
        }

        if (!$this->hasContact($range)) {
            return;
        }

        if ($range->start == $this->start) {
            $this->start = $range->end;
            return;
        }

        if ($range->end == $this->end) {
            $this->end = $range->start;
            return;
        }

        if ($range->start < $this->end) {
            $this->end = $range->start;
        } elseif ($range->end > $this->start) {
            $this->start = $range->end;
        }
    }

    public function getDifferenceArray(Range $range): array {
        if ($this->isSubset($range)) {
            return [];
        }

        if (!$this->hasContact($range)) {
            return [clone $this];
        }

        if ($this->isWithin($range)) {
            $result[1] = clone $result[0] = clone $this;
            $result[0]->end = $range->start;
            $result[1]->start = $range->end;
            return $result;
        }

        $result = clone $this;
        $result->subtract($range);
        return [$result];
    }

    public function format(string $format): array {
        return [
            $this->start->format($format),
            $this->end->format($format)
        ];
    }
}

class Ranges implements IteratorAggregate, Countable
{
    protected $ranges = [];

    public function __construct($ranges = null, DateTime $end = null) {
        if ($ranges) {
            if ($ranges instanceof DateTime) {
                if (null === $end) {
                    throw new InvalidArgumentException('Need start and end.');
                }
                $ranges = new Range($ranges, $end);
            }
            if ($ranges instanceof Range) {
                $ranges = [$ranges];
            }
            foreach ($ranges as $range) {
                $this->append($range);
            }
        }
    }

    public function getStart(): DateTime {
        if (!$this->ranges) {
            throw new BadMethodCallException('Empty Range');
        }
        return $this->ranges[0]->getStart();
    }

    public function getEnd(): DateTime {
        if (!$this->ranges) {
            throw new BadMethodCallException('Empty Range');
        }
        return $this->ranges[count($this->ranges) - 1]->getEnd();
    }

    public function append(Range $range): void {
        if ($this->ranges) {
            if ($range->getStart() <= $this->getEnd()) {
                throw new InvalidArgumentException('Cannot append range that is inside ranged time already');
            }
        }
        $this->ranges[] = $range;
    }

    public function subtractRange(Range $range): void {
        $result = new self();
        foreach ($this as $member) {
            /* @var Range $member */
            foreach ($member->getDifferenceArray($range) as $new) {
                $result->append($new);
            }
        }
        $this->ranges = $result->ranges;
    }

    public function subtract(Ranges $ranges): void {
        $result = clone $this;
        foreach ($ranges as $range) {
            $result->subtractRange($range);
        }
        $this->ranges = $result->ranges;
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->ranges);
    }

    public function getRange(): Range {
        return new Range($this->getStart(), $this->getEnd());
    }

    public function count(): int {
        return count($this->ranges);
    }
}

/*
$shift = new Ranges(new DateTime('14:30:00'), new DateTime('18:30:00'));

$unavailables = new Ranges([
    new Range(new DateTime('15:30:00'), new DateTime('16:30:00')),
    new Range(new DateTime('17:30:00'), new DateTime('18:30:00')),
]);

$shift->subtract($unavailables);

foreach ($shift as $range) {
    vprintf("%s - %s\n", $range->format('H:i:s'));
}
*/
