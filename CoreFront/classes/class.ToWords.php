<?php
declare(strict_types=1);

/**
 * NumberToWords - A PHP class to convert numeric values to words
 * 
 * Features:
 * - Converts numbers to words up to trillions
 * - Supports different currencies
 * - Handles decimal values
 * - Customizable formatting options
 */
class ToWords
{
    /**
     * Number mapping arrays
     */
    private array $units = [
        '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen'
    ];
    
    private array $tens = [
        '', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'
    ];
    
    private array $scales = [
        '', 'thousand', 'million', 'billion', 'trillion'
    ];
    
    /**
     * Currency configuration
     */
    private string $majorUnit;
    private string $minorUnit;
    private bool $includeAnd;
    private bool $includeCurrency;
    
    /**
     * Constructor
     * 
     * @param string $majorUnit Major currency unit (e.g., 'dollar', 'pound', 'euro')
     * @param string $minorUnit Minor currency unit (e.g., 'cent', 'penny')
     * @param bool $includeAnd Whether to include 'and' between hundreds and tens/units
     * @param bool $includeCurrency Whether to include currency names in output
     */
    public function __construct(
        string $majorUnit = 'dollar',
        string $minorUnit = 'cent',
        bool $includeAnd = true,
        bool $includeCurrency = true
    ) {
        $this->majorUnit = $majorUnit;
        $this->minorUnit = $minorUnit;
        $this->includeAnd = $includeAnd;
        $this->includeCurrency = $includeCurrency;
    }
    
    /**
     * Convert a number to words
     * 
     * @param float|int|string $number The number to convert
     * @param bool $pluralize Whether to pluralize currency units
     * @return string The number in words
     */
    public function convert(float|int|string $number, bool $pluralize = true): string
    {
        // Normalize the input
        $number = is_string($number) ? str_replace(['$', ','], '', $number) : $number;
        $number = number_format((float)$number, 2, '.', '');
        
        // Split into integer and decimal parts
        [$integerPart, $decimalPart] = explode('.', $number);
        
        // Handle zero
        if ((int)$integerPart === 0 && (int)$decimalPart === 0) {
            return "zero " . ($this->includeCurrency ? $this->pluralize($this->majorUnit, 0) : '');
        }
        
        // Convert integer part
        $result = $this->convertIntegerPart($integerPart);
        
        // Add currency unit if needed
        if ($this->includeCurrency) {
            $result .= ' ' . $this->pluralize($this->majorUnit, (int)$integerPart);
        }
        
        // Handle decimal part if not zero
        if ((int)$decimalPart > 0) {
            // Add 'and' if integer part exists
            if ((int)$integerPart > 0) {
                $result .= ' and ';
            }
            
            $result .= $this->convertIntegerPart($decimalPart);
            
            // Add minor currency unit if needed
            if ($this->includeCurrency) {
                $result .= ' ' . $this->pluralize($this->minorUnit, (int)$decimalPart);
            }
        }
        
        return trim(ucfirst($result));
    }
    
    /**
     * Convert integer part to words
     * 
     * @param string $number Integer as string
     * @return string Words representation
     */
    private function convertIntegerPart(string $number): string
    {
        // Remove leading zeros
        $number = ltrim($number, '0');
        
        if ($number === '') {
            return 'zero';
        }
        
        // Group by thousands
        $groups = [];
        $length = strlen($number);
        
        for ($i = $length; $i > 0; $i -= 3) {
            $chunk = substr($number, max(0, $i - 3), min(3, $i));
            $groups[] = $chunk;
        }
        
        // Process each group
        $result = '';
        foreach ($groups as $index => $group) {
            $group = (int)$group;
            
            if ($group === 0) {
                continue;
            }
            
            $groupText = $this->convertGroup($group);
            
            // Add scale (thousand, million, etc.)
            if ($index > 0 && !empty($groupText)) {
                $groupText .= ' ' . $this->scales[$index];
            }
            
            // Add to result with appropriate separator
            if ($result !== '') {
                $result = $groupText . ($this->includeAnd && $index === 0 && $group < 100 && strlen($result) > 0 ? ' and ' : ', ') . $result;
            } else {
                $result = $groupText;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert a group (1-999) to words
     * 
     * @param int $number Number between 1-999
     * @return string Words representation
     */
    private function convertGroup(int $number): string
    {
        $result = '';
        
        // Handle hundreds
        $hundreds = (int)($number / 100);
        if ($hundreds > 0) {
            $result .= $this->units[$hundreds] . ' hundred';
            $number %= 100;
            
            if ($number > 0 && $this->includeAnd) {
                $result .= ' and ';
            } elseif ($number > 0) {
                $result .= ' ';
            }
        }
        
        // Handle tens and units
        if ($number > 0) {
            if ($number < 20) {
                // For numbers less than 20, use the units array
                $result .= $this->units[$number];
            } else {
                // For numbers 20 and above
                $tensDigit = (int)($number / 10);
                $unitsDigit = $number % 10;
                
                $result .= $this->tens[$tensDigit];
                
                if ($unitsDigit > 0) {
                    $result .= '-' . $this->units[$unitsDigit];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Pluralize a word based on quantity
     * 
     * @param string $word Word to pluralize
     * @param int $count Count determining plurality
     * @return string Pluralized word
     */
    private function pluralize(string $word, int $count): string
    {
        if ($count === 1) {
            return $word;
        }
        
        // Handle special cases
        switch (strtolower($word)) {
            case 'penny':
                return 'pence';
            default:
                return $word;
        }
    }
    
    /**
     * Set currency units
     * 
     * @param string $majorUnit Major currency unit
     * @param string $minorUnit Minor currency unit
     * @return self
     */
    public function setCurrency(string $majorUnit, string $minorUnit): self
    {
        $this->majorUnit = $majorUnit;
        $this->minorUnit = $minorUnit;
        return $this;
    }
    
    /**
     * Configure whether to include 'and' between hundreds and tens/units
     * 
     * @param bool $includeAnd Whether to include 'and'
     * @return self
     */
    public function setIncludeAnd(bool $includeAnd): self
    {
        $this->includeAnd = $includeAnd;
        return $this;
    }
    
    /**
     * Configure whether to include currency names in output
     * 
     * @param bool $includeCurrency Whether to include currency
     * @return self
     */
    public function setIncludeCurrency(bool $includeCurrency): self
    {
        $this->includeCurrency = $includeCurrency;
        return $this;
    }
}