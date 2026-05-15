<?php

namespace Tests\Unit\Services\AI\Validators;

use App\Services\AI\Validators\ProgressionValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgressionValidatorTest extends TestCase
{
    public function test_progression_validator_uses_planned_day_specific_volume_instead_of_focus_distribution_proxy(): void
    {
        $validator = new ProgressionValidator();

        $candidatePlan = [
            'weekly_plan' => [[
                'day' => 'Segunda',
                'focus' => 'Upper Push',
                'exercises' => [
                    ['category' => 'specific', 'sets' => 2, 'remote_exercise_id' => '0030'],
                    ['category' => 'specific', 'sets' => 2, 'remote_exercise_id' => '0019'],
                    ['category' => 'specific', 'sets' => 3, 'remote_exercise_id' => '0025'],
                    ['category' => 'specific', 'sets' => 3, 'remote_exercise_id' => '0009'],
                    ['category' => 'cardio', 'sets' => 1, 'remote_exercise_id' => '0630'],
                ],
            ]],
        ];

        $context = [
            'planning_payload' => [
                'volume_distribution' => [
                    'peito' => ['sets_per_session' => 6],
                    'ombro' => ['sets_per_session' => 6],
                    'bracos' => ['sets_per_session' => 6],
                ],
                'selected_days' => [[
                    'label' => 'Segunda',
                    'focus_label' => 'Upper Push',
                    'allowed_focus_tokens' => ['peito', 'ombro', 'bracos'],
                    'selected_exercises' => [
                        ['category' => 'specific', 'sets' => 2, 'remote_exercise_id' => '0030'],
                        ['category' => 'specific', 'sets' => 2, 'remote_exercise_id' => '0019'],
                        ['category' => 'specific', 'sets' => 3, 'remote_exercise_id' => '0025'],
                        ['category' => 'specific', 'sets' => 3, 'remote_exercise_id' => '0009'],
                        ['category' => 'cardio', 'sets' => 1, 'remote_exercise_id' => '0630'],
                    ],
                ]],
            ],
        ];

        $validator->validate($candidatePlan, $context);

        $this->addToAssertionCount(1);
    }

    public function test_progression_validator_still_rejects_when_specific_volume_drops_below_planned_day_floor(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session volume fell below deterministic progression target for day: Upper Push');

        $validator = new ProgressionValidator();

        $validator->validate(
            [
                'weekly_plan' => [[
                    'day' => 'Segunda',
                    'focus' => 'Upper Push',
                    'exercises' => [
                        ['category' => 'specific', 'sets' => 1, 'remote_exercise_id' => '0030'],
                        ['category' => 'specific', 'sets' => 1, 'remote_exercise_id' => '0019'],
                        ['category' => 'specific', 'sets' => 2, 'remote_exercise_id' => '0025'],
                        ['category' => 'specific', 'sets' => 2, 'remote_exercise_id' => '0009'],
                        ['category' => 'cardio', 'sets' => 1, 'remote_exercise_id' => '0630'],
                    ],
                ]],
            ],
            [
                'planning_payload' => [
                    'selected_days' => [[
                        'label' => 'Segunda',
                        'focus_label' => 'Upper Push',
                        'selected_exercises' => [
                            ['category' => 'specific', 'sets' => 2, 'remote_exercise_id' => '0030'],
                            ['category' => 'specific', 'sets' => 2, 'remote_exercise_id' => '0019'],
                            ['category' => 'specific', 'sets' => 3, 'remote_exercise_id' => '0025'],
                            ['category' => 'specific', 'sets' => 3, 'remote_exercise_id' => '0009'],
                            ['category' => 'cardio', 'sets' => 1, 'remote_exercise_id' => '0630'],
                        ],
                    ]],
                ],
            ],
        );
    }
}