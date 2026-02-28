<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This abstract class serves as a foundation for request in the application.
 * It defines a structure for validating any request data (form | api) data, including expected fields,
 * validation rules, and human-readable labels. Subclasses must implement specific
 * methods to define their own validation criteria.
 *
 * @author BenIyke <beniyke34@gmail.com> | (twitter:@BigBeniyke)
 */

namespace Core;

use Core\Contracts\AuthServiceInterface;
use Helpers\Data\Data;
use Helpers\Validation\Validator;

abstract class BaseRequestValidation
{
    private array $requestData = [];

    private array $errors = [];

    private ?Data $validated = null;

    protected AuthServiceInterface $auth;

    private Validator $validator;

    public function __construct(AuthServiceInterface $auth, Validator $validator)
    {
        $this->auth = $auth;
        $this->validator = $validator;
    }

    /**
     * Processes the requestdata before validation or further processing.
     * This method can be overridden by child classes to implement custom
     * cleanup logic, such as trimming whitespace, removing unwanted fields,
     * or transforming data formats.
     */
    public function cleanup(array $requestData): array
    {
        return $requestData;
    }

    /**
     * Define the expected requestfields.
     * This method must be implemented by child classes.
     *
     * @return array<string> An array of expected requestfield names.
     */
    abstract public function expected(): array;

    /**
     * Define the validation rules for the requestfields.
     * This method must be implemented by child classes.
     *
     * @return array<string, mixed> An array of validation rules, where keys are field names and values are the rules.
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Define the human-readable names for requestfields.
     * This method must be implemented by child classes.
     *
     * @return array<string, string> An associative array where keys are field names and values are their human-readable labels.
     */
    public function parameters(): array
    {
        return [];
    }

    /**
     * Return an array of optional validation rules.
     * This method can be overridden in child classes if specific fields require optional validation.
     *
     * @return array<string> An array of requestfields that have optional validation rules.
     */
    public function optional(): array
    {
        return [];
    }

    /**
     * Return an array of not empty validation rules.
     * This method can be overridden in child classes if specific fields must not be empty.
     *
     * @return array<string> An array of requestfields that have not empty validation rules.
     */
    public function notempty(): array
    {
        return [];
    }

    /**
     * Return an array of custom validation messages.
     * This method can be overridden in child classes if specific validation messages need to be customized.
     *
     * @return array<string, string> An associative array of custom validation messages where keys are field names and values are the messages.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Specify requestfields to include in the validated fields payload, even though they may be empty or optional.
     * Child classes can override this method if additional fields need to be included in the validated fields payload,
     * even if they are empty or typically optional.
     *
     * @return array<string> An array of requestfield names to be included in the validated fields payload, even if they are empty or optional.
     */
    public function include(): array
    {
        return [];
    }

    /**
     * Specify requestfields to exclude from validation.
     * Child classes can override this method if exclusions are needed.
     *
     * @return array<string> An array of requestfield names to be excluded from validation.
     */
    public function exclude(): array
    {
        return [];
    }

    /**
     * Specify requestfields to modify after validation.
     * Child classes can override this method to apply specific modifications
     * to the requestfields, such as renaming field names.
     *
     * @return array<string> An array of modified requestfield names.
     */
    public function modify(): array
    {
        return [];
    }

    /**
     * Apply transformations to the requestdata before validation.
     * Can be overridden by child classes to modify requestdata.
     *
     * @param array<string, mixed> $data The requestdata to be transformed.
     *
     * @return array<string, mixed> The transformed requestdata.
     */
    public function transformData(array $data): array
    {
        return $data;
    }

    /**
     * Add additional data to be passed during validation.
     * Child classes can override this method to include extra data.
     *
     * @return array<string, mixed>|null Additional data for validation, or null if no additional data is needed.
     */
    public function additionalData(): ?array
    {
        return null;
    }

    /**
     * Define human-readable names for file upload fields.
     * Child classes can override this method for file-specific field labels.
     *
     * @return array<string, string> An associative array where keys are file field names and values are their human-readable labels.
     */
    public function file(): array
    {
        return [];
    }

    /**
     * Perform validation on the requestdata.
     */
    public function validate(array $requestData): void
    {
        $this->requestData = $requestData;
        $validator = $this->validator
            ->expected($this->expected(), $this->include())
            ->modify($this->modify())
            ->exclude($this->exclude())
            ->rules($this->rules())
            ->parameters($this->parameters())
            ->messages($this->messages())
            ->optional($this->optional())
            ->notempty($this->notempty())
            ->file($this->file())
            ->transform(function (array $data) {
                return $this->transformData($data);
            })
            ->validate($this->cleanup($requestData), $this->additionalData());

        $this->errors = $validator->errors();
        $this->validated = $validator->validated();
    }

    public function has_error(): bool
    {
        return ! empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): ?Data
    {
        return $this->validated;
    }

    protected function getRequestData(): Data
    {
        return Data::make($this->requestData);
    }
}
