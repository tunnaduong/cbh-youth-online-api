<?php

namespace Tests\Unit;

use App\Services\SchoolKnowledgeService;
use Tests\TestCase;

class SchoolKnowledgeServiceTest extends TestCase
{
    public function test_terms_drop_filler_words_and_repeats(): void
    {
        $this->assertSame(['hội', 'trại', '26', '3'], SchoolKnowledgeService::terms('Hội trại 26/3 của trường là khi nào, hội trại?'));
        $this->assertSame([], SchoolKnowledgeService::terms('trường Chuyên Biên Hòa'));
        $this->assertCount(6, SchoolKnowledgeService::terms('một hai ba bốn năm sáu bảy tám chín mười mươi'));
    }

    public function test_a_title_match_outweighs_a_body_match(): void
    {
        $terms = ['hội', 'trại'];

        $this->assertSame(8, SchoolKnowledgeService::score($terms, 'Hội trại 26/3', 'Thông báo về hội trại năm nay.'));
        $this->assertSame(2, SchoolKnowledgeService::score($terms, 'Thông báo', 'Kế hoạch hội trại.'));
        // Without accents it still matches.
        $this->assertSame(6, SchoolKnowledgeService::score(['hoi', 'trai'], 'Hội trại 26/3', ''));
    }

    public function test_several_words_need_more_than_one_match(): void
    {
        $this->assertSame(0, SchoolKnowledgeService::score(['hội', 'trại', 'kết'], 'Đại hội chi đoàn', ''));
        // Whole words, and accents count when they were typed.
        $this->assertSame(0, SchoolKnowledgeService::score(['hội'], 'Thời khóa biểu', 'Hỏi đáp'));
        $this->assertSame(3, SchoolKnowledgeService::score(['olympic'], 'Olympic tiếng Anh', ''));
        $this->assertSame(0, SchoolKnowledgeService::score(['olympic'], 'Giải bóng đá', 'Lịch thi đấu'));
    }

    public function test_the_excerpt_is_the_part_around_the_match(): void
    {
        $text = str_repeat('mở đầu ', 200) . 'lịch thi đấu bóng đá ' . str_repeat('kết thúc ', 200);
        $excerpt = SchoolKnowledgeService::excerpt($text, ['bóng']);

        $this->assertStringContainsString('lịch thi đấu bóng đá', $excerpt);
        $this->assertLessThanOrEqual(602, mb_strlen($excerpt));
        $this->assertStringStartsWith('…', $excerpt);
        $this->assertStringEndsWith('…', $excerpt);
        $this->assertSame('ngắn', SchoolKnowledgeService::excerpt('ngắn', ['x']));
    }

    public function test_markdown_becomes_plain_text(): void
    {
        $this->assertSame(
            'Tiêu đề Xem tại đây nhé',
            SchoolKnowledgeService::plainText("## Tiêu đề\n\n![ảnh](https://x/y.png) Xem [tại đây](https://x) **nhé**")
        );
    }
}
