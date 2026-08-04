<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix all comments nested deeper than 3 levels.
        // Level 1: replying_to IS NULL
        // Level 2: replying_to -> level 1
        // Level 3: replying_to -> level 2  (max allowed)
        // Level 4+: replying_to -> level 3+  (cap to level 2 ancestor)
        //
        // We run multiple passes because a level-5 comment can only be detected
        // after its level-4 parent has already been fixed to level 3.

        $maxPasses = 10;

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            // Find comments whose parent (p) itself has a parent (gp) that also
            // has a parent (ggp) — meaning this comment is at level 4+.
            // Cap replying_to to the grandparent (gp.id), which is level 2.
            $affected = DB::statement('
                UPDATE cyo_topic_comments AS c
                INNER JOIN cyo_topic_comments AS p  ON p.id  = c.replying_to
                INNER JOIN cyo_topic_comments AS gp ON gp.id = p.replying_to
                INNER JOIN cyo_topic_comments AS ggp ON ggp.id = gp.replying_to
                SET c.replying_to = gp.id
            ');

            // DB::statement() returns bool; use rowCount via a select to detect convergence
            $remaining = DB::select('
                SELECT COUNT(*) as cnt
                FROM cyo_topic_comments AS c
                INNER JOIN cyo_topic_comments AS p  ON p.id  = c.replying_to
                INNER JOIN cyo_topic_comments AS gp ON gp.id = p.replying_to
                INNER JOIN cyo_topic_comments AS ggp ON ggp.id = gp.replying_to
            ');

            if ($remaining[0]->cnt === 0) {
                break;
            }
        }
    }

    public function down(): void
    {
        // Not reversible — original deeply-nested replying_to values are not stored.
    }
};
