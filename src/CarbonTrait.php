<?php

/*
 * This file is part of the CarbonInterface package.
 *
 * (c) Brian Nesbitt <brian@nesbot.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cake\Chronos;

use Closure;
use DateTimeInterface;
use DateTimeZone;
use DateTime;
use DatePeriod;
use InvalidArgumentException;
use LogicException;

/**
 * A simple API extension for DateTimeInterface
 *
 */
trait CarbonTrait
{

    /**
     * Names of days of the week.
     *
     * @var array
     */
    protected static $days = [
        CarbonInterface::SUNDAY    => 'Sunday',
        CarbonInterface::MONDAY    => 'Monday',
        CarbonInterface::TUESDAY   => 'Tuesday',
        CarbonInterface::WEDNESDAY => 'Wednesday',
        CarbonInterface::THURSDAY  => 'Thursday',
        CarbonInterface::FRIDAY    => 'Friday',
        CarbonInterface::SATURDAY  => 'Saturday',
    ];

    /**
     * Terms used to detect if a time passed is a relative date for testing purposes
     *
     * @var array
     */
    protected static $relativeKeywords = [
        'this',
        'next',
        'last',
        'tomorrow',
        'yesterday',
        '+',
        '-',
        'first',
        'last',
        'ago',
    ];

    /**
     * Format to use for __toString method when type juggling occurs.
     *
     * @var string
     */
    protected static $toStringFormat = CarbonInterface::DEFAULT_TO_STRING_FORMAT;


    /**
     * First day of week
     *
     * @var int
     */
    protected static $weekStartsAt = CarbonInterface::MONDAY;

    /**
     * Last day of week
     *
     * @var int
     */
    protected static $weekEndsAt = CarbonInterface::SUNDAY;

    /**
     * Days of weekend
     *
     * @var array
     */
    protected static $weekendDays = [CarbonInterface::SATURDAY, CarbonInterface::SUNDAY];

    /**
     * A test CarbonInterface instance to be returned when now instances are created
     *
     * @var Carbon
     */
    protected static $testNow;


    /**
     * Creates a DateTimeZone from a string or a DateTimeZone
     *
     * @param DateTimeZone|string|null $object
     *
     * @return DateTimeZone
     *
     * @throws InvalidArgumentException
     */
    protected static function safeCreateDateTimeZone($object)
    {
        if ($object === null) {
            // Don't return null... avoid Bug #52063 in PHP <5.3.6
            return new DateTimeZone(date_default_timezone_get());
        }

        if ($object instanceof DateTimeZone) {
            return $object;
        }

        $tz = @timezone_open((string) $object);

        if ($tz === false) {
            throw new InvalidArgumentException('Unknown or bad timezone ('.$object.')');
        }

        return $tz;
    }

    ///////////////////////////////////////////////////////////////////
    //////////////////////////// CONSTRUCTORS /////////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Create a CarbonInterface instance from a DateTimeInterface one
     *
     * @param DateTimeInterface $dt
     *
     * @return static
     */
    public static function instance(DateTimeInterface $dt)
    {
        if ($dt instanceof static) {
            return clone $dt;
        }
        return new static($dt->format('Y-m-d H:i:s.u'), $dt->getTimeZone());
    }

    /**
     * Create a CarbonInterface instance from a string.  This is an alias for the
     * constructor that allows better fluent syntax as it allows you to do
     * CarbonInterface::parse('Monday next week')->fn() rather than
     * (new Carbon('Monday next week'))->fn()
     *
     * @param string              $time
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function parse($time = null, $tz = null)
    {
        return new static($time, $tz);
    }

    /**
     * Get a CarbonInterface instance for the current date and time
     *
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function now($tz = null)
    {
        return new static(null, $tz);
    }

    /**
     * Create a CarbonInterface instance for today
     *
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function today($tz = null)
    {
        return static::now($tz)->startOfDay();
    }

    /**
     * Create a CarbonInterface instance for tomorrow
     *
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function tomorrow($tz = null)
    {
        return static::today($tz)->addDay();
    }

    /**
     * Create a CarbonInterface instance for yesterday
     *
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function yesterday($tz = null)
    {
        return static::today($tz)->subDay();
    }

    /**
     * Create a CarbonInterface instance for the greatest supported date.
     *
     * @return Carbon
     */
    public static function maxValue()
    {
        return static::createFromTimestamp(PHP_INT_MAX);
    }

    /**
     * Create a CarbonInterface instance for the lowest supported date.
     *
     * @return Carbon
     */
    public static function minValue()
    {
        $max = PHP_INT_SIZE === 32 ? PHP_INT_MAX : PHP_INT_MAX / 10;
        return static::createFromTimestamp(~$max);
    }

    /**
     * Create a new CarbonInterface instance from a specific date and time.
     *
     * If any of $year, $month or $day are set to null their now() values
     * will be used.
     *
     * If $hour is null it will be set to its now() value and the default values
     * for $minute and $second will be their now() values.
     * If $hour is not null then the default values for $minute and $second
     * will be 0.
     *
     * @param integer             $year
     * @param integer             $month
     * @param integer             $day
     * @param integer             $hour
     * @param integer             $minute
     * @param integer             $second
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function create($year = null, $month = null, $day = null, $hour = null, $minute = null, $second = null, $tz = null)
    {
        $year = ($year === null) ? date('Y') : $year;
        $month = ($month === null) ? date('n') : $month;
        $day = ($day === null) ? date('j') : $day;

        if ($hour === null) {
            $hour = date('G');
            $minute = ($minute === null) ? date('i') : $minute;
            $second = ($second === null) ? date('s') : $second;
        } else {
            $minute = ($minute === null) ? 0 : $minute;
            $second = ($second === null) ? 0 : $second;
        }

        return static::createFromFormat('Y-n-j G:i:s', sprintf('%s-%s-%s %s:%02s:%02s', $year, $month, $day, $hour, $minute, $second), $tz);
    }

    /**
     * Create a CarbonInterface instance from just a date. The time portion is set to now.
     *
     * @param integer             $year
     * @param integer             $month
     * @param integer             $day
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function createFromDate($year = null, $month = null, $day = null, $tz = null)
    {
        return static::create($year, $month, $day, null, null, null, $tz);
    }

    /**
     * Create a CarbonInterface instance from just a time. The date portion is set to today.
     *
     * @param integer             $hour
     * @param integer             $minute
     * @param integer             $second
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function createFromTime($hour = null, $minute = null, $second = null, $tz = null)
    {
        return static::create(null, null, null, $hour, $minute, $second, $tz);
    }

    /**
     * Create a CarbonInterface instance from a specific format
     *
     * @param string              $format
     * @param string              $time
     * @param DateTimeZone|string $tz
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public static function createFromFormat($format, $time, $tz = null)
    {
        if ($tz !== null) {
            $dt = parent::createFromFormat($format, $time, static::safeCreateDateTimeZone($tz));
        } else {
            $dt = parent::createFromFormat($format, $time);
        }

        if ($dt instanceof DateTimeInterface) {
            return static::instance($dt);
        }

        $errors = static::getLastErrors();
        throw new InvalidArgumentException(implode(PHP_EOL, $errors['errors']));
    }

    /**
     * Create a CarbonInterface instance from a timestamp
     *
     * @param integer             $timestamp
     * @param DateTimeZone|string $tz
     *
     * @return static
     */
    public static function createFromTimestamp($timestamp, $tz = null)
    {
        return static::now($tz)->setTimestamp($timestamp);
    }

    /**
     * Create a CarbonInterface instance from an UTC timestamp
     *
     * @param integer $timestamp
     *
     * @return static
     */
    public static function createFromTimestampUTC($timestamp)
    {
        return new static('@'.$timestamp);
    }

    /**
     * Get a copy of the instance
     *
     * @return static
     */
    public function copy()
    {
        return static::instance($this);
    }

    ///////////////////////////////////////////////////////////////////
    ///////////////////////// GETTERS AND SETTERS /////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Get a part of the CarbonInterface object
     *
     * @param string $name
     *
     * @throws InvalidArgumentException
     *
     * @return string|integer|DateTimeZone
     */
    public function __get($name)
    {
        switch (true) {
            case array_key_exists($name, $formats = [
                'year' => 'Y',
                'yearIso' => 'o',
                'month' => 'n',
                'day' => 'j',
                'hour' => 'G',
                'minute' => 'i',
                'second' => 's',
                'micro' => 'u',
                'dayOfWeek' => 'w',
                'dayOfYear' => 'z',
                'weekOfYear' => 'W',
                'daysInMonth' => 't',
                'timestamp' => 'U',
            ]):
                return (int) $this->format($formats[$name]);

            case $name === 'weekOfMonth':
                return (int) ceil($this->day / CarbonInterface::DAYS_PER_WEEK);

            case $name === 'age':
                return (int) $this->diffInYears();

            case $name === 'quarter':
                return (int) ceil($this->month / 3);

            case $name === 'offset':
                return $this->getOffset();

            case $name === 'offsetHours':
                return $this->getOffset() / CarbonInterface::SECONDS_PER_MINUTE / CarbonInterface::MINUTES_PER_HOUR;

            case $name === 'dst':
                return $this->format('I') == '1';

            case $name === 'local':
                return $this->offset == $this->copy()->setTimezone(date_default_timezone_get())->offset;

            case $name === 'utc':
                return $this->offset == 0;

            case $name === 'timezone' || $name === 'tz':
                return $this->getTimezone();

            case $name === 'timezoneName' || $name === 'tzName':
                return $this->getTimezone()->getName();

            default:
                throw new InvalidArgumentException(sprintf("Unknown getter '%s'", $name));
        }
    }

    /**
     * Check if an attribute exists on the object
     *
     * @param string $name
     *
     * @return boolean
     */
    public function __isset($name)
    {
        try {
            $this->__get($name);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * Set the instance's year
     *
     * @param integer $value
     *
     * @return static
     */
    public function year($value)
    {
        return $this->setDate($value, $this->month, $this->day);
    }

    /**
     * Set the instance's month
     *
     * @param integer $value
     *
     * @return static
     */
    public function month($value)
    {
        return $this->setDate($this->year, $value, $this->day);
    }

    /**
     * Set the instance's day
     *
     * @param integer $value
     *
     * @return static
     */
    public function day($value)
    {
        return $this->setDate($this->year, $this->month, $value);
    }

    /**
     * Set the instance's hour
     *
     * @param integer $value
     *
     * @return static
     */
    public function hour($value)
    {
        return $this->setTime($value, $this->minute, $this->second);
    }

    /**
     * Set the instance's minute
     *
     * @param integer $value
     *
     * @return static
     */
    public function minute($value)
    {
        return $this->setTime($this->hour, $value, $this->second);
    }

    /**
     * Set the instance's second
     *
     * @param integer $value
     *
     * @return static
     */
    public function second($value)
    {
        return $this->setTime($this->hour, $this->minute, $value);
    }

    /**
     * Set the date and time all together
     *
     * @param integer $year
     * @param integer $month
     * @param integer $day
     * @param integer $hour
     * @param integer $minute
     * @param integer $second
     *
     * @return static
     */
    public function setDateTime($year, $month, $day, $hour, $minute, $second = 0)
    {
        return $this->setDate($year, $month, $day)->setTime($hour, $minute, $second);
    }

    /**
     * Set the instance's timestamp
     *
     * @param integer $value
     *
     * @return static
     */
    public function timestamp($value)
    {
        return parent::setTimestamp($value);
    }

    /**
     * Alias for setTimezone()
     *
     * @param DateTimeZone|string $value
     *
     * @return static
     */
    public function timezone($value)
    {
        return $this->setTimezone($value);
    }

    /**
     * Alias for setTimezone()
     *
     * @param DateTimeZone|string $value
     *
     * @return static
     */
    public function tz($value)
    {
        return $this->setTimezone($value);
    }

    /**
     * Set the instance's timezone from a string or object
     *
     * @param DateTimeZone|string $value
     *
     * @return static
     */
    public function setTimezone($value)
    {
        return parent::setTimezone(static::safeCreateDateTimeZone($value));
    }

    ///////////////////////////////////////////////////////////////////
    /////////////////////// WEEK SPECIAL DAYS /////////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Get the first day of week
     *
     * @return int
     */
    public static function getWeekStartsAt()
    {
        return static::$weekStartsAt;
    }

    /**
     * Set the first day of week
     *
     * @param int
     */
    public static function setWeekStartsAt($day)
    {
        static::$weekStartsAt = $day;
    }

    /**
     * Get the last day of week
     *
     * @return int
     */
    public static function getWeekEndsAt()
    {
        return static::$weekEndsAt;
    }

    /**
     * Set the first day of week
     *
     * @param int
     */
    public static function setWeekEndsAt($day)
    {
        static::$weekEndsAt = $day;
    }

    /**
     * Get weekend days
     *
     * @return array
     */
    public static function getWeekendDays()
    {
        return static::$weekendDays;
    }

    /**
     * Set weekend days
     *
     * @param array
     */
    public static function setWeekendDays($days)
    {
        static::$weekendDays = $days;
    }

    ///////////////////////////////////////////////////////////////////
    ///////////////////////// TESTING AIDS ////////////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Set a CarbonInterface instance (real or mock) to be returned when a "now"
     * instance is created.  The provided instance will be returned
     * specifically under the following conditions:
     *   - A call to the static now() method, ex. CarbonInterface::now()
     *   - When a null (or blank string) is passed to the constructor or parse(), ex. new Carbon(null)
     *   - When the string "now" is passed to the constructor or parse(), ex. new Carbon('now')
     *
     * Note the timezone parameter was left out of the examples above and
     * has no affect as the mock value will be returned regardless of its value.
     *
     * To clear the test instance call this method using the default
     * parameter of null.
     *
     * @param CarbonInterface $testNow
     */
    public static function setTestNow(CarbonInterface $testNow = null)
    {
        static::$testNow = $testNow;
    }

    /**
     * Get the CarbonInterface instance (real or mock) to be returned when a "now"
     * instance is created.
     *
     * @return static the current instance used for testing
     */
    public static function getTestNow()
    {
        return static::$testNow;
    }

    /**
     * Determine if there is a valid test instance set. A valid test instance
     * is anything that is not null.
     *
     * @return boolean true if there is a test instance, otherwise false
     */
    public static function hasTestNow()
    {
        return static::getTestNow() !== null;
    }

    /**
     * Determine if there is a relative keyword in the time string, this is to
     * create dates relative to now for test instances. e.g.: next tuesday
     *
     * @param string $time
     *
     * @return boolean true if there is a keyword, otherwise false
     */
    public static function hasRelativeKeywords($time)
    {
        // skip common format with a '-' in it
        if (preg_match('/[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}/', $time) !== 1) {
            foreach (static::$relativeKeywords as $keyword) {
                if (stripos($time, $keyword) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    ///////////////////////////////////////////////////////////////////
    /////////////////////// STRING FORMATTING /////////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Format the instance with the current locale.  You can set the current
     * locale using setlocale() http://php.net/setlocale.
     *
     * @param string $format
     *
     * @return string
     */
    public function formatLocalized($format)
    {
        // Check for Windows to find and replace the %e
        // modifier correctly
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
            $format = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $format);
        }

        return strftime($format, strtotime($this));
    }

    /**
     * Reset the format used to the default when type juggling a CarbonInterface instance to a string
     *
     */
    public static function resetToStringFormat()
    {
        static::setToStringFormat(CarbonInterface::DEFAULT_TO_STRING_FORMAT);
    }

    /**
     * Set the default format used when type juggling a CarbonInterface instance to a string
     *
     * @param string $format
     */
    public static function setToStringFormat($format)
    {
        static::$toStringFormat = $format;
    }

    /**
     * Format the instance as a string using the set format
     *
     * @return string
     */
    public function __toString()
    {
        return $this->format(static::$toStringFormat);
    }

    /**
     * Format the instance as date
     *
     * @return string
     */
    public function toDateString()
    {
        return $this->format('Y-m-d');
    }

    /**
     * Format the instance as a readable date
     *
     * @return string
     */
    public function toFormattedDateString()
    {
        return $this->format('M j, Y');
    }

    /**
     * Format the instance as time
     *
     * @return string
     */
    public function toTimeString()
    {
        return $this->format('H:i:s');
    }

    /**
     * Format the instance as date and time
     *
     * @return string
     */
    public function toDateTimeString()
    {
        return $this->format('Y-m-d H:i:s');
    }

    /**
     * Format the instance with day, date and time
     *
     * @return string
     */
    public function toDayDateTimeString()
    {
        return $this->format('D, M j, Y g:i A');
    }

    /**
     * Format the instance as ATOM
     *
     * @return string
     */
    public function toAtomString()
    {
        return $this->format(DateTime::ATOM);
    }

    /**
     * Format the instance as COOKIE
     *
     * @return string
     */
    public function toCookieString()
    {
        return $this->format(DateTime::COOKIE);
    }

    /**
     * Format the instance as ISO8601
     *
     * @return string
     */
    public function toIso8601String()
    {
        return $this->format(DateTime::ISO8601);
    }

    /**
     * Format the instance as RFC822
     *
     * @return string
     */
    public function toRfc822String()
    {
        return $this->format(DateTime::RFC822);
    }

    /**
     * Format the instance as RFC850
     *
     * @return string
     */
    public function toRfc850String()
    {
        return $this->format(DateTime::RFC850);
    }

    /**
     * Format the instance as RFC1036
     *
     * @return string
     */
    public function toRfc1036String()
    {
        return $this->format(DateTime::RFC1036);
    }

    /**
     * Format the instance as RFC1123
     *
     * @return string
     */
    public function toRfc1123String()
    {
        return $this->format(DateTime::RFC1123);
    }

    /**
     * Format the instance as RFC2822
     *
     * @return string
     */
    public function toRfc2822String()
    {
        return $this->format(DateTime::RFC2822);
    }

    /**
     * Format the instance as RFC3339
     *
     * @return string
     */
    public function toRfc3339String()
    {
        return $this->format(DateTime::RFC3339);
    }

    /**
     * Format the instance as RSS
     *
     * @return string
     */
    public function toRssString()
    {
        return $this->format(DateTime::RSS);
    }

    /**
     * Format the instance as W3C
     *
     * @return string
     */
    public function toW3cString()
    {
        return $this->format(DateTime::W3C);
    }

    ///////////////////////////////////////////////////////////////////
    ////////////////////////// COMPARISONS ////////////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Determines if the instance is equal to another
     *
     * @param CarbonInterface $dt
     *
     * @return boolean
     */
    public function eq(CarbonInterface $dt)
    {
        return $this == $dt;
    }

    /**
     * Determines if the instance is not equal to another
     *
     * @param CarbonInterface $dt
     *
     * @return boolean
     */
    public function ne(CarbonInterface $dt)
    {
        return !$this->eq($dt);
    }

    /**
     * Determines if the instance is greater (after) than another
     *
     * @param CarbonInterface $dt
     *
     * @return boolean
     */
    public function gt(CarbonInterface $dt)
    {
        return $this > $dt;
    }

    /**
     * Determines if the instance is greater (after) than or equal to another
     *
     * @param CarbonInterface $dt
     *
     * @return boolean
     */
    public function gte(CarbonInterface $dt)
    {
        return $this >= $dt;
    }

    /**
     * Determines if the instance is less (before) than another
     *
     * @param CarbonInterface $dt
     *
     * @return boolean
     */
    public function lt(CarbonInterface $dt)
    {
        return $this < $dt;
    }

    /**
     * Determines if the instance is less (before) or equal to another
     *
     * @param CarbonInterface $dt
     *
     * @return boolean
     */
    public function lte(CarbonInterface $dt)
    {
        return $this <= $dt;
    }

    /**
     * Determines if the instance is between two others
     *
     * @param  CarbonInterface  $dt1
     * @param  CarbonInterface  $dt2
     * @param  boolean $equal  Indicates if a > and < comparison should be used or <= or >=
     *
     * @return boolean
     */
    public function between(CarbonInterface $dt1, CarbonInterface $dt2, $equal = true)
    {
        if ($dt1->gt($dt2)) {
            $temp = $dt1;
            $dt1 = $dt2;
            $dt2 = $temp;
        }

        if ($equal) {
            return $this->gte($dt1) && $this->lte($dt2);
        } else {
            return $this->gt($dt1) && $this->lt($dt2);
        }
    }

    /**
     * Get the minimum instance between a given instance (default now) and the current instance.
     *
     * @param CarbonInterface $dt
     *
     * @return static
     */
    public function min(CarbonInterface $dt = null)
    {
        $dt = ($dt === null) ? static::now($this->tz) : $dt;

        return $this->lt($dt) ? $this : $dt;
    }

    /**
     * Get the maximum instance between a given instance (default now) and the current instance.
     *
     * @param CarbonInterface $dt
     *
     * @return static
     */
    public function max(CarbonInterface $dt = null)
    {
        $dt = ($dt === null) ? static::now($this->tz) : $dt;

        return $this->gt($dt) ? $this : $dt;
    }

    /**
     * Determines if the instance is a weekday
     *
     * @return boolean
     */
    public function isWeekday()
    {
        return !$this->isWeekend();
    }

    /**
     * Determines if the instance is a weekend day
     *
     * @return boolean
     */
    public function isWeekend()
    {
        return in_array($this->dayOfWeek, self::$weekendDays);
    }

    /**
     * Determines if the instance is yesterday
     *
     * @return boolean
     */
    public function isYesterday()
    {
        return $this->toDateString() === static::yesterday($this->tz)->toDateString();
    }

    /**
     * Determines if the instance is today
     *
     * @return boolean
     */
    public function isToday()
    {
        return $this->toDateString() === static::now($this->tz)->toDateString();
    }

    /**
     * Determines if the instance is tomorrow
     *
     * @return boolean
     */
    public function isTomorrow()
    {
        return $this->toDateString() === static::tomorrow($this->tz)->toDateString();
    }

    /**
     * Determines if the instance is in the future, ie. greater (after) than now
     *
     * @return boolean
     */
    public function isFuture()
    {
        return $this->gt(static::now($this->tz));
    }

    /**
     * Determines if the instance is in the past, ie. less (before) than now
     *
     * @return boolean
     */
    public function isPast()
    {
        return $this->lt(static::now($this->tz));
    }

    /**
     * Determines if the instance is a leap year
     *
     * @return boolean
     */
    public function isLeapYear()
    {
        return $this->format('L') == '1';
    }

    /**
     * Checks if the passed in date is the same day as the instance current day.
     *
     * @param  CarbonInterface  $dt
     * @return boolean
     */
    public function isSameDay(CarbonInterface $dt)
    {
        return $this->toDateString() === $dt->toDateString();
    }

    /**
     * Checks if this day is a Sunday.
     *
     * @return boolean
     */
    public function isSunday()
    {
        return $this->dayOfWeek === CarbonInterface::SUNDAY;
    }

    /**
     * Checks if this day is a Monday.
     *
     * @return boolean
     */
    public function isMonday()
    {
        return $this->dayOfWeek === CarbonInterface::MONDAY;
    }

    /**
     * Checks if this day is a Tuesday.
     *
     * @return boolean
     */
    public function isTuesday()
    {
        return $this->dayOfWeek === CarbonInterface::TUESDAY;
    }

    /**
     * Checks if this day is a Wednesday.
     *
     * @return boolean
     */
    public function isWednesday()
    {
        return $this->dayOfWeek === CarbonInterface::WEDNESDAY;
    }

    /**
     * Checks if this day is a Thursday.
     *
     * @return boolean
     */
    public function isThursday()
    {
        return $this->dayOfWeek === CarbonInterface::THURSDAY;
    }

    /**
     * Checks if this day is a Friday.
     *
     * @return boolean
     */
    public function isFriday()
    {
        return $this->dayOfWeek === CarbonInterface::FRIDAY;
    }

    /**
     * Checks if this day is a Saturday.
     *
     * @return boolean
     */
    public function isSaturday()
    {
        return $this->dayOfWeek === CarbonInterface::SATURDAY;
    }

    ///////////////////////////////////////////////////////////////////
    /////////////////// ADDITIONS AND SUBTRACTIONS ////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Add years to the instance. Positive $value travel forward while
     * negative $value travel into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addYears($value)
    {
        return $this->modify((int) $value.' year');
    }

    /**
     * Add a year to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addYear($value = 1)
    {
        return $this->addYears($value);
    }

    /**
     * Remove a year from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subYear($value = 1)
    {
        return $this->subYears($value);
    }

    /**
     * Remove years from the instance.
     *
     * @param integer $value
     *
     * @return static
     */
    public function subYears($value)
    {
        return $this->addYears(-1 * $value);
    }

    /**
     * Add months to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addMonths($value)
    {
        return $this->modify((int) $value.' month');
    }

    /**
     * Add a month to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addMonth($value = 1)
    {
        return $this->addMonths($value);
    }

    /**
     * Remove a month from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subMonth($value = 1)
    {
        return $this->subMonths($value);
    }

    /**
     * Remove months from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subMonths($value)
    {
        return $this->addMonths(-1 * $value);
    }

    /**
     * Add months without overflowing to the instance. Positive $value
     * travels forward while negative $value travels into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addMonthsNoOverflow($value)
    {
        $date = $this->copy()->addMonths($value);

        if ($date->day != $this->day) {
            return $date
                ->day(1)
                ->subMonth()
                ->endOfMonth();
        } else {
            return $date;
        }
    }

    /**
     * Add a month with no overflow to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addMonthNoOverflow($value = 1)
    {
        return $this->addMonthsNoOverflow($value);
    }

    /**
     * Remove a month with no overflow from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subMonthNoOverflow($value = 1)
    {
        return $this->subMonthsNoOverflow($value);
    }

    /**
     * Remove months with no overflow from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subMonthsNoOverflow($value)
    {
        return $this->addMonthsNoOverflow(-1 * $value);
    }

    /**
     * Add days to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addDays($value)
    {
        return $this->modify((int) $value.' day');
    }

    /**
     * Add a day to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addDay($value = 1)
    {
        return $this->addDays($value);
    }

    /**
     * Remove a day from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subDay($value = 1)
    {
        return $this->subDays($value);
    }

    /**
     * Remove days from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subDays($value)
    {
        return $this->addDays(-1 * $value);
    }

    /**
     * Add weekdays to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addWeekdays($value)
    {
        return $this->modify((int) $value.' weekday');
    }

    /**
     * Add a weekday to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addWeekday($value = 1)
    {
        return $this->addWeekdays($value);
    }

    /**
     * Remove a weekday from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subWeekday($value = 1)
    {
        return $this->subWeekdays($value);
    }

    /**
     * Remove weekdays from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subWeekdays($value)
    {
        return $this->addWeekdays(-1 * $value);
    }

    /**
     * Add weeks to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addWeeks($value)
    {
        return $this->modify((int) $value.' week');
    }

    /**
     * Add a week to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addWeek($value = 1)
    {
        return $this->addWeeks($value);
    }

    /**
     * Remove a week from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subWeek($value = 1)
    {
        return $this->subWeeks($value);
    }

    /**
     * Remove weeks to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subWeeks($value)
    {
        return $this->addWeeks(-1 * $value);
    }

    /**
     * Add hours to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addHours($value)
    {
        return $this->modify((int) $value.' hour');
    }

    /**
     * Add an hour to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addHour($value = 1)
    {
        return $this->addHours($value);
    }

    /**
     * Remove an hour from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subHour($value = 1)
    {
        return $this->subHours($value);
    }

    /**
     * Remove hours from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subHours($value)
    {
        return $this->addHours(-1 * $value);
    }

    /**
     * Add minutes to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addMinutes($value)
    {
        return $this->modify((int) $value.' minute');
    }

    /**
     * Add a minute to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addMinute($value = 1)
    {
        return $this->addMinutes($value);
    }

    /**
     * Remove a minute from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subMinute($value = 1)
    {
        return $this->subMinutes($value);
    }

    /**
     * Remove minutes from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subMinutes($value)
    {
        return $this->addMinutes(-1 * $value);
    }

    /**
     * Add seconds to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param integer $value
     *
     * @return static
     */
    public function addSeconds($value)
    {
        return $this->modify((int) $value.' second');
    }

    /**
     * Add a second to the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function addSecond($value = 1)
    {
        return $this->addSeconds($value);
    }

    /**
     * Remove a second from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subSecond($value = 1)
    {
        return $this->subSeconds($value);
    }

    /**
     * Remove seconds from the instance
     *
     * @param integer $value
     *
     * @return static
     */
    public function subSeconds($value)
    {
        return $this->addSeconds(-1 * $value);
    }

    ///////////////////////////////////////////////////////////////////
    /////////////////////////// DIFFERENCES ///////////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Get the difference in years
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return integer
     */
    public function diffInYears(CarbonInterface $dt = null, $abs = true)
    {
        $dt = ($dt === null) ? static::now($this->tz) : $dt;

        return (int) $this->diff($dt, $abs)->format('%r%y');
    }

    /**
     * Get the difference in months
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return integer
     */
    public function diffInMonths(CarbonInterface $dt = null, $abs = true)
    {
        $dt = ($dt === null) ? static::now($this->tz) : $dt;

        return $this->diffInYears($dt, $abs) * CarbonInterface::MONTHS_PER_YEAR + (int) $this->diff($dt, $abs)->format('%r%m');
    }

    /**
     * Get the difference in weeks
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return integer
     */
    public function diffInWeeks(CarbonInterface $dt = null, $abs = true)
    {
        return (int) ($this->diffInDays($dt, $abs) / CarbonInterface::DAYS_PER_WEEK);
    }

    /**
     * Get the difference in days
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return integer
     */
    public function diffInDays(CarbonInterface $dt = null, $abs = true)
    {
        $dt = ($dt === null) ? static::now($this->tz) : $dt;

        return (int) $this->diff($dt, $abs)->format('%r%a');
    }

    /**
     * Get the difference in days using a filter closure
     *
     * @param Closure $callback
     * @param CarbonInterface  $dt
     * @param boolean $abs      Get the absolute of the difference
     *
     * @return int
     */
    public function diffInDaysFiltered(Closure $callback, CarbonInterface $dt = null, $abs = true)
    {
        return $this->diffFiltered(CarbonInterval::day(), $callback, $dt, $abs);
    }

    /**
     * Get the difference in hours using a filter closure
     *
     * @param Closure $callback
     * @param CarbonInterface  $dt
     * @param boolean $abs      Get the absolute of the difference
     *
     * @return int
     */
    public function diffInHoursFiltered(Closure $callback, CarbonInterface $dt = null, $abs = true)
    {
        return $this->diffFiltered(CarbonInterval::hour(), $callback, $dt, $abs);
    }

    /**
     * Get the difference by the given interval using a filter closure
     *
     * @param CarbonInterval $ci An interval to traverse by
     * @param Closure $callback
     * @param CarbonInterface  $dt
     * @param boolean $abs      Get the absolute of the difference
     *
     * @return int
     */
    public function diffFiltered(CarbonInterval $ci, Closure $callback, CarbonInterface $dt = null, $abs = true)
    {
        $start = $this;
        $end = ($dt === null) ? static::now($this->tz) : $dt;
        $inverse = false;

        if ($end < $start) {
            $start = $end;
            $end = $this;
            $inverse = true;
        }

        $period = new DatePeriod($start, $ci, $end);
        $vals = array_filter(iterator_to_array($period), function (DateTimeInterface $date) use ($callback) {
            return call_user_func($callback, static::instance($date));
        });

        $diff = count($vals);

        return $inverse && !$abs ? -$diff : $diff;
    }

    /**
     * Get the difference in weekdays
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return int
     */
    public function diffInWeekdays(CarbonInterface $dt = null, $abs = true)
    {
        return $this->diffInDaysFiltered(function (CarbonInterface $date) {
            return $date->isWeekday();
        }, $dt, $abs);
    }

    /**
     * Get the difference in weekend days using a filter
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return int
     */
    public function diffInWeekendDays(CarbonInterface $dt = null, $abs = true)
    {
        return $this->diffInDaysFiltered(function (CarbonInterface $date) {
            return $date->isWeekend();
        }, $dt, $abs);
    }

    /**
     * Get the difference in hours
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return integer
     */
    public function diffInHours(CarbonInterface $dt = null, $abs = true)
    {
        return (int) ($this->diffInSeconds($dt, $abs) / CarbonInterface::SECONDS_PER_MINUTE / CarbonInterface::MINUTES_PER_HOUR);
    }

    /**
     * Get the difference in minutes
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return integer
     */
    public function diffInMinutes(CarbonInterface $dt = null, $abs = true)
    {
        return (int) ($this->diffInSeconds($dt, $abs) / CarbonInterface::SECONDS_PER_MINUTE);
    }

    /**
     * Get the difference in seconds
     *
     * @param CarbonInterface  $dt
     * @param boolean $abs Get the absolute of the difference
     *
     * @return integer
     */
    public function diffInSeconds(CarbonInterface $dt = null, $abs = true)
    {
        $dt = ($dt === null) ? static::now($this->tz) : $dt;
        $value = $dt->getTimestamp() - $this->getTimestamp();

        return $abs ? abs($value) : $value;
    }

    /**
     * The number of seconds since midnight.
     *
     * @return integer
     */
    public function secondsSinceMidnight()
    {
        return $this->diffInSeconds($this->copy()->startOfDay());
    }

    /**
     * The number of seconds until 23:23:59.
     *
     * @return integer
     */
    public function secondsUntilEndOfDay()
    {
        return $this->diffInSeconds($this->copy()->endOfDay());
    }

    ///////////////////////////////////////////////////////////////////
    //////////////////////////// MODIFIERS ////////////////////////////
    ///////////////////////////////////////////////////////////////////

    /**
     * Resets the time to 00:00:00
     *
     * @return static
     */
    public function startOfDay()
    {
        return $this->hour(0)->minute(0)->second(0);
    }

    /**
     * Resets the time to 23:59:59
     *
     * @return static
     */
    public function endOfDay()
    {
        return $this->hour(23)->minute(59)->second(59);
    }

    /**
     * Resets the date to the first day of the month and the time to 00:00:00
     *
     * @return static
     */
    public function startOfMonth()
    {
        return $this->startOfDay()->day(1);
    }

    /**
     * Resets the date to end of the month and time to 23:59:59
     *
     * @return static
     */
    public function endOfMonth()
    {
        return $this->day($this->daysInMonth)->endOfDay();
    }

    /**
     * Resets the date to the first day of the year and the time to 00:00:00
     *
     * @return static
     */
    public function startOfYear()
    {
        return $this->month(1)->startOfMonth();
    }

    /**
     * Resets the date to end of the year and time to 23:59:59
     *
     * @return static
     */
    public function endOfYear()
    {
        return $this->month(CarbonInterface::MONTHS_PER_YEAR)->endOfMonth();
    }

    /**
     * Resets the date to the first day of the decade and the time to 00:00:00
     *
     * @return static
     */
    public function startOfDecade()
    {
        return $this->startOfYear()->year($this->year - $this->year % CarbonInterface::YEARS_PER_DECADE);
    }

    /**
     * Resets the date to end of the decade and time to 23:59:59
     *
     * @return static
     */
    public function endOfDecade()
    {
        return $this->endOfYear()->year($this->year - $this->year % CarbonInterface::YEARS_PER_DECADE + CarbonInterface::YEARS_PER_DECADE - 1);
    }

    /**
     * Resets the date to the first day of the century and the time to 00:00:00
     *
     * @return static
     */
    public function startOfCentury()
    {
        return $this->startOfYear()->year($this->year - $this->year % CarbonInterface::YEARS_PER_CENTURY);
    }

    /**
     * Resets the date to end of the century and time to 23:59:59
     *
     * @return static
     */
    public function endOfCentury()
    {
        return $this->endOfYear()->year($this->year - $this->year % CarbonInterface::YEARS_PER_CENTURY + CarbonInterface::YEARS_PER_CENTURY - 1);
    }

    /**
     * Resets the date to the first day of week (defined in $weekStartsAt) and the time to 00:00:00
     *
     * @return static
     */
    public function startOfWeek()
    {
        $dt = $this;
        if ($dt->dayOfWeek != static::$weekStartsAt) {
            $dt = $dt->previous(static::$weekStartsAt);
        }

        return $dt->startOfDay();
    }

    /**
     * Resets the date to end of week (defined in $weekEndsAt) and time to 23:59:59
     *
     * @return static
     */
    public function endOfWeek()
    {
        $dt = $this;
        if ($dt->dayOfWeek != static::$weekEndsAt) {
            $dt = $dt->next(static::$weekEndsAt);
        }

        return $dt->endOfDay();
    }

    /**
     * Modify to the next occurrence of a given day of the week.
     * If no dayOfWeek is provided, modify to the next occurrence
     * of the current day of the week.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function next($dayOfWeek = null)
    {
        if ($dayOfWeek === null) {
            $dayOfWeek = $this->dayOfWeek;
        }

        return $this->startOfDay()->modify('next '.static::$days[$dayOfWeek]);
    }

    /**
     * Modify to the previous occurrence of a given day of the week.
     * If no dayOfWeek is provided, modify to the previous occurrence
     * of the current day of the week.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function previous($dayOfWeek = null)
    {
        if ($dayOfWeek === null) {
            $dayOfWeek = $this->dayOfWeek;
        }

        return $this->startOfDay()->modify('last '.static::$days[$dayOfWeek]);
    }

    /**
     * Modify to the first occurrence of a given day of the week
     * in the current month. If no dayOfWeek is provided, modify to the
     * first day of the current month.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function firstOfMonth($dayOfWeek = null)
    {
        $dt = $this->startOfDay();
        if ($dayOfWeek === null) {
            return $dt->day(1);
        }

        return $dt->modify('first '.static::$days[$dayOfWeek].' of '.$dt->format('F').' '.$dt->year);
    }

    /**
     * Modify to the last occurrence of a given day of the week
     * in the current month. If no dayOfWeek is provided, modify to the
     * last day of the current month.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function lastOfMonth($dayOfWeek = null)
    {
        $dt = $this->startOfDay();
        if ($dayOfWeek === null) {
            return $dt->day($dt->daysInMonth);
        }

        return $dt->modify('last '.static::$days[$dayOfWeek].' of '.$dt->format('F').' '.$dt->year);
    }

    /**
     * Modify to the given occurrence of a given day of the week
     * in the current month. If the calculated occurrence is outside the scope
     * of the current month, then return false and no modifications are made.
     * Use the supplied consts to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $nth
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function nthOfMonth($nth, $dayOfWeek)
    {
        $dt = $this->copy()->firstOfMonth();
        $check = $dt->format('Y-m');
        $dt = $dt->modify('+'.$nth.' '.static::$days[$dayOfWeek]);

        return ($dt->format('Y-m') === $check) ? $this->modify($dt) : false;
    }

    /**
     * Modify to the first occurrence of a given day of the week
     * in the current quarter. If no dayOfWeek is provided, modify to the
     * first day of the current quarter.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function firstOfQuarter($dayOfWeek = null)
    {
        return $this->day(1)->month($this->quarter * 3 - 2)->firstOfMonth($dayOfWeek);
    }

    /**
     * Modify to the last occurrence of a given day of the week
     * in the current quarter. If no dayOfWeek is provided, modify to the
     * last day of the current quarter.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function lastOfQuarter($dayOfWeek = null)
    {
        return $this->day(1)->month($this->quarter * 3)->lastOfMonth($dayOfWeek);
    }

    /**
     * Modify to the given occurrence of a given day of the week
     * in the current quarter. If the calculated occurrence is outside the scope
     * of the current quarter, then return false and no modifications are made.
     * Use the supplied consts to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $nth
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function nthOfQuarter($nth, $dayOfWeek)
    {
        $dt = $this->copy()->day(1)->month($this->quarter * 3);
        $last_month = $dt->month;
        $year = $dt->year;
        $dt = $dt->firstOfQuarter()->modify('+'.$nth.' '.static::$days[$dayOfWeek]);

        return ($last_month < $dt->month || $year !== $dt->year) ? false : $this->modify($dt);
    }

    /**
     * Modify to the first occurrence of a given day of the week
     * in the current year. If no dayOfWeek is provided, modify to the
     * first day of the current year.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function firstOfYear($dayOfWeek = null)
    {
        return $this->month(1)->firstOfMonth($dayOfWeek);
    }

    /**
     * Modify to the last occurrence of a given day of the week
     * in the current year. If no dayOfWeek is provided, modify to the
     * last day of the current year.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function lastOfYear($dayOfWeek = null)
    {
        return $this->month(CarbonInterface::MONTHS_PER_YEAR)->lastOfMonth($dayOfWeek);
    }

    /**
     * Modify to the given occurrence of a given day of the week
     * in the current year. If the calculated occurrence is outside the scope
     * of the current year, then return false and no modifications are made.
     * Use the supplied consts to indicate the desired dayOfWeek, ex. CarbonInterface::MONDAY.
     *
     * @param int $nth
     * @param int $dayOfWeek
     *
     * @return mixed
     */
    public function nthOfYear($nth, $dayOfWeek)
    {
        $dt = $this->copy()->firstOfYear()->modify('+'.$nth.' '.static::$days[$dayOfWeek]);

        return $this->year == $dt->year ? $this->modify($dt) : false;
    }

    /**
     * Modify the current instance to the average of a given instance (default now) and the current instance.
     *
     * @param CarbonInterface $dt
     *
     * @return static
     */
    public function average(CarbonInterface $dt = null)
    {
        $dt = ($dt === null) ? static::now($this->tz) : $dt;

        return $this->addSeconds((int) ($this->diffInSeconds($dt, false) / 2));
    }

    /**
     * Check if its the birthday. Compares the date/month values of the two dates.
     *
     * @param CarbonInterface $dt
     *
     * @return boolean
     */
    public function isBirthday(CarbonInterface $dt)
    {
        return $this->format('md') === $dt->format('md');
    }

    /**
     * Check if instance of CarbonInterface is mutable.
     *
     * @return boolean
     */
    public function isMutable()
    {
        return $this instanceof DateTime;
    }
}
