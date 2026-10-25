<?php

namespace Tests\Unit;

use App\Models\Topic;
use Tests\TestCase;

class TopicFromMembersTest extends TestCase
{
  public function test_from_members_scope_excludes_teacher_and_school_account_but_keeps_admin(): void
  {
    $query = Topic::fromMembers();
    $sql = $query->toSql();
    $bindings = $query->getBindings();

    $this->assertStringContainsString('not in', strtolower($sql));
    $this->assertStringContainsString('cyo_auth_accounts', $sql);

    $this->assertContains('teacher', $bindings);
    $this->assertContains(config('forum.school_account') ?: 'DoanTruongCBH', $bindings);
    $this->assertNotContains('admin', $bindings);
  }
}
