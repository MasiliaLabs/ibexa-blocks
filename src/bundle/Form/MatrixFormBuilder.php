<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Form;

use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A Symfony form from the rows of a matrix field: one question per row (its label, its type, whether it is required, the choices
 * of a select, a radio or a group of checkboxes), then the captcha when asked for and the submit button. The name of the field
 * of a question is its label, lower-cased, with anything but letters and digits as "_".
 */
final readonly class MatrixFormBuilder
{
    public function __construct(private FormFactoryInterface $formFactory)
    {
    }

    /**
     * @param list<array{label: string, type: string, isRequired?: bool, values?: string}> $rows
     * @param array<string, mixed>                                                       $options Options of the form itself
     */
    public function build(array $rows, array $options = [], bool $captcha = false): FormInterface
    {
        $builder = $this->formFactory->createBuilder(options: $options);

        foreach ($rows as $row) {
            $row = [
                'label' => (string) $row['label'],
                'type' => (string) ($row['type'] ?? 'text'),
                'isRequired' => (bool) ($row['isRequired'] ?? false),
                'values' => (string) ($row['values'] ?? ''),
            ] + $row;
            $options = $this->fieldOptions($row);
            $options['allow_extra_fields'] = $row['label'];
            $builder->add(self::fieldName($row['label']), $this->formType($row['type'], $row), $options);
        }

        if ($captcha && class_exists(CaptchaType::class)) {
            $builder->add('captcha', CaptchaType::class, [
                'label' => 'contact.form.security_code',
                'translation_domain' => 'messages',
                'required' => true,
                'invalid_message' => 'contact.form.captcha.invalid',
                'attr' => ['class' => 'form-control captcha-input', 'placeholder' => 'contact.form.captcha.placeholder'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label'],
                'as_url' => true,
                'reload' => true,
                'quality' => 90,
                'width' => 180,
                'height' => 50,
                'length' => 5,
                'distortion' => true,
                'background_color' => [255, 255, 255],
                'text_color' => [73, 80, 87],
            ]);
        }

        $builder->add('send', SubmitType::class, [
            'attr' => ['class' => 'btn btn btn-primary'],
            'label' => 'contact.cta',
            'row_attr' => ['class' => 'form-group form-submit'],
        ])->setMethod('POST');

        return $builder->getForm();
    }

    public static function fieldName(string $label): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '_', $label));
    }

    /** @param array<string, mixed> $row */
    private function formType(string $type, array $row): string
    {
        if ($type === 'checkbox' && ($row['values'] ?? '') !== '') {
            return ChoiceType::class;
        }

        return match ($type) {
            'text' => TextType::class,
            'email' => EmailType::class,
            'textarea' => TextareaType::class,
            'checkbox' => CheckboxType::class,
            'radio', 'select' => ChoiceType::class,
            'tel' => TelType::class,
            'number' => NumberType::class,
            'date' => DateType::class,
            'file', 'attachment' => FileType::class,
            default => TextType::class,
        };
    }

    /** @return array<string, string> The choices of a "a, b, c" list, each the label of itself */
    private function choices(string $values): array
    {
        $choices = array_map(trim(...), explode(',', $values));

        return array_combine($choices, $choices);
    }

    /** @param array<string, mixed> $row @return list<object> */
    private function constraints(array $row): array
    {
        $constraints = [];
        if ($row['isRequired']) {
            $constraints[] = new NotBlank(message: sprintf('Please enter your %s', strtolower((string) $row['label'])));
        }
        if ($row['type'] === 'email') {
            $constraints[] = new Email(message: 'Please enter a valid email address');
        }
        if (in_array($row['type'], ['file', 'attachment'], true)) {
            $constraints[] = new File(maxSize: '10M', mimeTypes: ['application/pdf'], mimeTypesMessage: 'Please upload a valid document');
        }

        return $constraints;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function fieldOptions(array $row): array
    {
        $options = [
            'label' => $row['label'],
            'required' => (bool) $row['isRequired'],
            'constraints' => $this->constraints($row),
            'row_attr' => ['class' => 'form-group'],
            'attr' => ['class' => 'form-control', 'placeholder' => $row['label']],
            'label_attr' => ['class' => 'form-label'],
        ];
        if (!empty($row['placeholder'])) {
            $options['attr']['placeholder'] = $row['placeholder'];
        }

        switch ($row['type']) {
            case 'radio':
                $options['expanded'] = true;
                $options['multiple'] = false;
                $options['choices'] = $this->choices($row['values'] !== '' ? $row['values'] : 'Yes,No');
                $options['attr']['class'] = 'form-check-input';
                $options['label_attr']['class'] = 'form-check-label';
                $options['choice_attr'] = static fn (): array => ['class' => 'form-check-input'];
                $options['label_html'] = true;
                break;
            case 'select':
                $options['expanded'] = false;
                $options['multiple'] = false;
                $options['choices'] = $this->choices($row['values']);
                $options['placeholder'] = 'Choose an option';
                break;
            case 'checkbox':
                $options['required'] = false;
                $options['attr']['class'] = 'form-check-input';
                $options['label_attr']['class'] = 'form-check-label';
                if ($row['values'] !== '') {
                    $options['expanded'] = true;
                    $options['multiple'] = true;
                    $options['choices'] = $this->choices($row['values']);
                    $options['row_attr']['class'] = 'form-group form-check-group';
                    $options['choice_attr'] = static fn (): array => ['class' => 'form-check-input'];
                    $options['label_html'] = true;
                } else {
                    $options['row_attr']['class'] = 'form-group form-check';
                }
                break;
            case 'date':
                $options['widget'] = 'single_text';
                $options['html5'] = true;
                break;
            case 'number':
                $options['html5'] = true;
                foreach (['min', 'max'] as $bound) {
                    if (isset($row[$bound])) {
                        $options['attr'][$bound] = $row[$bound];
                    }
                }
                break;
            case 'file':
            case 'attachment':
                $options['attr']['accept'] = '.pdf,.doc,.docx/*';
                $options['attr']['class'] = 'form-control-file';
                break;
        }

        return $options;
    }
}
