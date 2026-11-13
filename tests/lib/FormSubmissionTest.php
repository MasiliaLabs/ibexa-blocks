<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\lib;

use Masilia\IbexaBlocks\Form\FormSettings;
use Masilia\IbexaBlocks\Form\FormSubmission;
use Masilia\IbexaBlocks\Search\SearchRequest;
use Masilia\IbexaBlocks\Search\SearchSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormSubmissionTest extends TestCase
{
    public function testASubmissionIsTheFormAndItsAnswersAndNothingThatDecidesWhereItGoes(): void
    {
        $submission = FormSubmission::fromArray([
            'formId' => '42',
            'formData' => ['Name' => 'Lea', 'Topics' => ['a', 'b'], 'Nested' => [['x']]],
            'recipientEmail' => 'attacker@example.org',
            'senderEmail' => 'ceo@example.org',
        ], new FormSettings());

        self::assertSame(42, $submission->formId);
        self::assertSame(['Name' => 'Lea', 'Topics' => 'a, b', 'Nested' => ''], $submission->answers);
        self::assertFalse(property_exists($submission, 'recipient'), 'there is no recipient to read from a request');
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalid(): iterable
    {
        yield 'no form' => [['formData' => ['a' => 'b']], 'Unknown form.'];
        yield 'a form that is not a number' => [['formId' => 'abc', 'formData' => ['a' => 'b']], 'Unknown form.'];
        yield 'no answers' => [['formId' => 1, 'formData' => []], 'No form data provided.'];
        yield 'answers that are not a map' => [['formId' => 1, 'formData' => 'x'], 'No form data provided.'];
        yield 'too long an answer' => [['formId' => 1, 'formData' => ['a' => str_repeat('x', 20)]], 'An answer is too long.'];
        yield 'too many fields' => [['formId' => 1, 'formData' => ['a' => '1', 'b' => '2', 'c' => '3']], 'Too many fields.'];
    }

    #[DataProvider('invalid')]
    public function testAnInvalidSubmissionIsRefusedWithAMessageForTheVisitor(array $body, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        FormSubmission::fromArray($body, new FormSettings(maxFields: 2, maxLength: 10));
    }

    public function testSettingsAreReadWithTheirDefaults(): void
    {
        $settings = FormSettings::fromSettings(['content_types' => ['steps_form'], 'from_address' => 'no-reply@x.org']);

        self::assertSame(['steps_form'], $settings->contentTypes);
        self::assertSame('no-reply@x.org', $settings->fromAddress);
        self::assertSame('recipients_email', $settings->recipientsField);
        self::assertNull(FormSettings::fromSettings(['from_address' => ''])->fromAddress);
    }

    public function testASearchRequestIsHeldToWhatTheSiteAllows(): void
    {
        $settings = SearchSettings::fromSettings(['allowed_content_types' => ['article'], 'api' => ['max_items' => 20]]);

        $request = SearchRequest::fromArray(['content_types' => ['article', 'user', 'folder'], 'limit' => 5000, 'page' => -3, 'sort' => 'DROP', 'parent_subtree' => '/1/2/', 'tags' => 'x'], $settings);

        self::assertSame(['article'], $request->contentTypes, 'a type that is not allowed is dropped');
        self::assertSame(20, $request->limit);
        self::assertSame(1, $request->page);
        self::assertSame('published_desc', $request->sort, 'a sort the site does not know is the default');
        self::assertSame([], $request->tags);
        self::assertSame(['article'], SearchRequest::fromArray(['content_types' => ['user']], $settings)->contentTypes, 'nothing allowed left: the default types');
    }
}
