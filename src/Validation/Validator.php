<?php

namespace Mini\Framework\Validation;

use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator as BaseValidator;
use Psr\Http\Message\UploadedFileInterface;

class Validator extends BaseValidator
{
    /**
     * {@inheritdoc}
     */
    public function validateMimes($attribute, $value, $parameters)
    {
        if (! $this->isValidFileInstance($value)) {
            return false;
        }

        if ($this->shouldBlockPhpUpload($value, $parameters)) {
            return false;
        }

        if (in_array('jpg', $parameters) || in_array('jpeg', $parameters)) {
            $parameters = array_unique(array_merge($parameters, ['jpg', 'jpeg']));
        }

        return $value->getStream()->getMetadata('uri') !== '' && in_array($this->getClientOriginalExtension($value), $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateAttribute($attribute, $rule)
    {
        $this->currentRule = $rule;

        [$rule, $parameters] = ValidationRuleParser::parse($rule);

        if ($rule === '') {
            return;
        }

        // First we will get the correct keys for the given attribute in case the field is nested in
        // an array. Then we determine if the given rule accepts other field names as parameters.
        // If so, we will replace any asterisks found in the parameters with the correct keys.
        if ($this->dependsOnOtherFields($rule)) {
            $parameters = $this->replaceDotInParameters($parameters);

            if ($keys = $this->getExplicitKeys($attribute)) {
                $parameters = $this->replaceAsterisksInParameters($parameters, $keys);
            }
        }

        $value = $this->getValue($attribute);

        // If the attribute is a file, we will verify that the file upload was actually successful
        // and if it wasn't we will add a failure for the attribute. Files may not successfully
        // upload if they are too large based on PHP's settings so we will bail in this case.
        if ($value instanceof UploadedFileInterface && ! $this->isValidFile($value) &&
            $this->hasRule($attribute, array_merge($this->fileRules, $this->implicitRules))
        ) {
            return $this->addFailure($attribute, 'uploaded', []);
        }

        // If we have made it this far we will make sure the attribute is validatable and if it is
        // we will call the validation method with the attribute. If a method returns false the
        // attribute is invalid and we will add a failure message for this failing attribute.
        $validatable = $this->isValidatable($rule, $attribute, $value);

        if ($rule instanceof RuleContract) {
            return $validatable
                ? $this->validateUsingCustomRule($attribute, $value, $rule)
                : null;
        }

        $method = "validate{$rule}";

        if ($validatable && ! $this->$method($attribute, $value, $parameters, $this)) {
            $this->addFailure($attribute, $rule, $parameters);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getSize($attribute, $value)
    {
        $hasNumeric = $this->hasRule($attribute, $this->numericRules);

        // This method will determine if the attribute is a number, string, or file and
        // return the proper size accordingly. If it is a number, then number itself
        // is the size. If it is a file, we take kilobytes, and for a string the
        // entire length of the string will be considered the attribute size.
        if (is_numeric($value) && $hasNumeric) {
            return $value;
        } elseif (is_array($value)) {
            return count($value);
        } elseif ($value instanceof UploadedFileInterface) {
            return $value->getSize() / 1024;
        }

        return mb_strlen($value ?? '');
    }

    /**
     * {@inheritdoc}
     */
    public function isValidFileInstance($value)
    {
        if ($value instanceof UploadedFileInterface && ! $this->isValidFile($value)) {
            return false;
        }

        return $value instanceof UploadedFileInterface;
    }

    /**
     * {@inheritdoc}
     */
    protected function shouldBlockPhpUpload($value, $parameters)
    {
        if (in_array('php', $parameters)) {
            return false;
        }

        $phpExtensions = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        ];

        return ($value instanceof UploadedFileInterface)
           ? in_array(trim(strtolower($this->getClientOriginalExtension($value))), $phpExtensions)
           : in_array(trim(strtolower($value->getExtension())), $phpExtensions);
    }

    /**
     * Returns whether the file has been uploaded with HTTP and no error occurred.
     *
     * @return bool
     */
    protected function isValidFile(UploadedFileInterface $file)
    {
        return $file->getError() === \UPLOAD_ERR_OK && is_uploaded_file($file->getStream()->getMetadata('uri'));
    }

    /**
     * Returns the original file extension.
     *
     * It is extracted from the original file name that was uploaded.
     * Then it should not be considered as a safe value.
     *
     * @return string
     */
    protected function getClientOriginalExtension(UploadedFileInterface $file)
    {
        return pathinfo($file->getClientFilename(), \PATHINFO_EXTENSION);
    }
}
