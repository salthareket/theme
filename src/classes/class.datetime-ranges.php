<?php
/**
 * DateTimeRange & DateTimeRanges
 * Time range manipulation: overlap detection, subtraction, merging, gap finding.
 *
 * Originally based on hakre's gist, fully rewritten and extended.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * Works with both TIME-ONLY and FULL DATE+TIME.
 *
 * // ── Time-only examples ──
 *
 * $shift = new Range(new DateTime('09:00'), new DateTime('18:00'));
 * $shift->contains(new DateTime('12:00'));              // true
 * $shift->getDurationMinutes();                         // 540
 * $shift->getDurationFormatted();                       // "9 saat"
 * (string) $shift;                                      // "09:00 - 18:00"
 *
 * // ── Date+Time examples ──
 *
 * $event = new Range(new DateTime('2025-06-01 10:00'), new DateTime('2025-06-03 18:00'));
 * $event->contains(new DateTime('2025-06-02 14:00'));   // true
 * $event->getDurationFormatted();                       // "2 gün 8 saat"
 * $event->format('Y-m-d H:i');                          // ['2025-06-01 10:00', '2025-06-03 18:00']
 *
 * // Quick create from strings
 * $booking = Range::fromStrings('2025-07-10 14:00', '2025-07-10 16:00');
 *
 * // ── Overlap detection ──
 *
 * $room_a = Range::fromStrings('2025-06-01 09:00', '2025-06-01 12:00');
 * $room_b = Range::fromStrings('2025-06-01 11:00', '2025-06-01 14:00');
 * $room_a->overlaps($room_b);                          // true (11:00-12:00 arası çakışıyor)
 *
 * // ── Subtract unavailable times from a shift ──
 *
 * $workday = new Ranges(new DateTime('2025-06-01 09:00'), new DateTime('2025-06-01 18:00'));
 * $breaks = new Ranges([
 *     Range::fromStrings('2025-06-01 12:00', '2025-06-01 13:00'),  // lunch
 *     Range::fromStrings('2025-06-01 15:00', '2025-06-01 15:15'),  // tea break
 * ]);
 * $workday->subtract($breaks);
 * foreach ($workday as $range) {
 *     vprintf("%s - %s\n", $range->format('H:i'));
 * }
 * // 09:00 - 12:00
 * // 13:00 - 15:00
 * // 15:15 - 18:00
 *
 * // ── Multi-day: find gaps between appointments ──
 *
 * $appointments = new Ranges([
 *     Range::fromStrings('2025-06-01 09:00', '2025-06-01 10:00'),
 *     Range::fromStrings('2025-06-01 14:00', '2025-06-01 15:00'),
 * ]);
 * $gaps = $appointments->getGaps();
 * // gap: 10:00 - 14:00 (4 saat boşluk)
 *
 * // ── Merge overlapping ranges ──
 *
 * $merged = Ranges::merge([
 *     Range::fromStrings('2025-06-01 09:00', '2025-06-01 11:00'),
 *     Range::fromStrings('2025-06-01 10:00', '2025-06-01 12:00'),
 *     Range::fromStrings('2025-06-02 14:00', '2025-06-02 16:00'),
 * ]);
 * // result: 2 ranges → 2025-06-01 09:00-12:00, 2025-06-02 14:00-16:00
 *
 * // ── Total duration ──
 * $workday->getTotalMinutes();                          // toplam çalışma dakikası
 *
 * ──────────────────────────────────────────────────────────
 */

class Range
{
    protected DateTime $start;
    protected DateTime $end;

    public function __construct(DateTime $start, DateTime $end) {
        if ($end < $start) {
            throw new InvalidArgumentException('End date cannot be before start date.');
        }
        $this->start = $start;
        $this->end = $end;
    }

    public function getStart(): DateTime {
        return $this->start;
    }

    public function setStart(DateTime $start): void {
        if ($start > $this->end) {
            throw new InvalidArgumentException('Start date cannot be after end date.');
        }
        $this->start = $start;
    }

    public function getEnd(): DateTime {
        return $this->end;
    }

    public function setEnd(DateTime $end): void {
        if ($end < $this->start) {
            throw new InvalidArgumentException('End date cannot be before start date.');
        }
        $this->end = $end;
    }

    /**
     * Does this range contain a specific time?
     */
    public function contains(DateTime $time): bool {
        return $this->start <= $time && $this->end >= $time;
    }

    /** @deprecated Use contains() */
    public function hasTime(DateTime $time): bool {
        return $this->contains($time);
    }

    /**
     * Do two ranges overlap or touch?
     */
    public function overlaps(Range $range): bool {
        return $this->start < $range->end && $this->end > $range->start;
    }

    /**
     * Do two ranges touch at edges?
     */
    public function touches(Range $range): bool {
        return $this->start == $range->end || $this->end == $range->start;
    }

    /** @deprecated Use overlaps() */
    public function hasContact(Range $range): bool {
        return $this->contains($range->start) || $this->contains($range->end);
    }

    public function isSame(Range $range): bool {
        return $this->start == $range->start && $this->end == $range->end;
    }

    /**
     * Is the given range fully inside this range?
     */
    public function isWithin(Range $range): bool {
        return $range->start > $this->start && $range->end < $this->end;
    }

    /**
     * Is this range fully inside the given range?
     */
    public function isSubset(Range $range): bool {
        return $range->contains($this->start) && $range->contains($this->end);
    }

    /**
     * Extend this range to include another overlapping range
     */
    public function add(Range $range): void {
        if (!$this->overlaps($range) && !$this->touches($range)) {
            throw new InvalidArgumentException('Range needs to overlap or touch.');
        }
        if ($range->start < $this->start) $this->start = $range->start;
        if ($range->end > $this->end) $this->end = $range->end;
    }

    /**
     * Cut a range out of this range (from one side)
     */
    public function subtract(Range $range): void {
        if ($this->isWithin($range)) {
            throw new InvalidArgumentException('Range would divide — use getDifferenceArray() instead.');
        }
        if ($this->isSubset($range)) {
            throw new InvalidArgumentException('Range would delete entirely.');
        }
        if (!$this->overlaps($range) && !$this->touches($range)) {
            return;
        }

        if ($range->start <= $this->start) {
            $this->start = $range->end;
        } elseif ($range->end >= $this->end) {
            $this->end = $range->start;
        }
    }

    /**
     * Returns remaining pieces after cutting out a range
     * @return Range[]
     */
    public function getDifferenceArray(Range $range): array {
        if ($this->isSubset($range)) {
            return [];
        }
        if (!$this->overlaps($range)) {
            return [clone $this];
        }
        if ($this->isWithin($range)) {
            $left = clone $this;
            $left->end = $range->start;
            $right = clone $this;
            $right->start = $range->end;
            return [$left, $right];
        }

        $result = clone $this;
        $result->subtract($range);
        return [$result];
    }

    /**
     * Duration in minutes
     */
    public function getDurationMinutes(): int {
        return (int) abs($this->end->getTimestamp() - $this->start->getTimestamp()) / 60;
    }

    /**
     * Duration in seconds
     */
    public function getDurationSeconds(): int {
        return abs($this->end->getTimestamp() - $this->start->getTimestamp());
    }

    /**
     * Human readable duration
     */
    public function getDurationFormatted(): string {
        $diff = $this->start->diff($this->end);
        $parts = [];
        if ($diff->d > 0) $parts[] = $diff->d . ' gün';
        if ($diff->h > 0) $parts[] = $diff->h . ' saat';
        if ($diff->i > 0) $parts[] = $diff->i . ' dakika';
        return implode(' ', $parts) ?: '0 dakika';
    }

    /**
     * @return string[]  [start_formatted, end_formatted]
     */
    public function format(string $format): array {
        return [$this->start->format($format), $this->end->format($format)];
    }

    public function __toString(): string {
        return $this->start->format('H:i') . ' - ' . $this->end->format('H:i');
    }

    /**
     * Create from string pair
     */
    public static function fromStrings(string $start, string $end): self {
        return new self(new DateTime($start), new DateTime($end));
    }
}

class Ranges implements IteratorAggregate, Countable
{
    /** @var Range[] */
    protected array $ranges = [];

    public function __construct($ranges = null, DateTime $end = null) {
        if ($ranges instanceof DateTime) {
            if ($end === null) {
                throw new InvalidArgumentException('Need start and end.');
            }
            $ranges = [new Range($ranges, $end)];
        }
        if ($ranges instanceof Range) {
            $ranges = [$ranges];
        }
        if (is_array($ranges)) {
            foreach ($ranges as $range) {
                $this->append($range);
            }
        }
    }

    public function isEmpty(): bool {
        return empty($this->ranges);
    }

    public function getStart(): DateTime {
        if (!$this->ranges) throw new BadMethodCallException('Empty Ranges');
        return $this->ranges[0]->getStart();
    }

    public function getEnd(): DateTime {
        if (!$this->ranges) throw new BadMethodCallException('Empty Ranges');
        return $this->ranges[count($this->ranges) - 1]->getEnd();
    }

    public function append(Range $range): void {
        if ($this->ranges) {
            if ($range->getStart() < $this->getEnd()) {
                throw new InvalidArgumentException('Ranges must be appended in chronological order. Use Ranges::merge() for unsorted input.');
            }
        }
        $this->ranges[] = $range;
    }

    public function subtractRange(Range $range): void {
        $result = [];
        foreach ($this->ranges as $member) {
            foreach ($member->getDifferenceArray($range) as $new) {
                $result[] = $new;
            }
        }
        $this->ranges = $result;
    }

    public function subtract(Ranges $ranges): void {
        foreach ($ranges as $range) {
            $this->subtractRange($range);
        }
    }

    /**
     * Find gaps between ranges
     * @return Range[]
     */
    public function getGaps(): array {
        $gaps = [];
        $count = count($this->ranges);
        for ($i = 0; $i < $count - 1; $i++) {
            $gap_start = $this->ranges[$i]->getEnd();
            $gap_end = $this->ranges[$i + 1]->getStart();
            if ($gap_start < $gap_end) {
                $gaps[] = new Range(clone $gap_start, clone $gap_end);
            }
        }
        return $gaps;
    }

    /**
     * Total duration of all ranges in minutes
     */
    public function getTotalMinutes(): int {
        $total = 0;
        foreach ($this->ranges as $range) {
            $total += $range->getDurationMinutes();
        }
        return $total;
    }

    /**
     * Merge overlapping/touching ranges from unsorted input
     * @param Range[] $ranges
     * @return Ranges
     */
    public static function merge(array $ranges): self {
        if (empty($ranges)) return new self();

        usort($ranges, function (Range $a, Range $b) {
            return $a->getStart() <=> $b->getStart();
        });

        $merged = [$ranges[0]];
        $count = count($ranges);
        for ($i = 1; $i < $count; $i++) {
            $last = $merged[count($merged) - 1];
            $current = $ranges[$i];
            if ($last->overlaps($current) || $last->touches($current)) {
                $last->add($current);
            } else {
                $merged[] = $current;
            }
        }

        $result = new self();
        $result->ranges = $merged;
        return $result;
    }

    public function getRange(): Range {
        return new Range($this->getStart(), $this->getEnd());
    }

    public function getIterator(): \ArrayIterator {
        return new \ArrayIterator($this->ranges);
    }

    public function count(): int {
        return count($this->ranges);
    }

    /**
     * @return Range[]
     */
    public function toArray(): array {
        return $this->ranges;
    }
}
