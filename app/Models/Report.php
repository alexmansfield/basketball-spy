<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use SoftDeletes;

    /**
     * The rating sections and their subsections.
     * This defines the structure for the ratings JSON column.
     */
    public const RATING_STRUCTURE = [
        'offense' => [
            'shooting' => 'Shooting',
            'driving' => 'Driving',
            'dribbling' => 'Dribbling',
            'creating' => 'Creating',
            'passing' => 'Passing',
            'finishing' => 'Finishing',
        ],
        'defense' => [
            'one_on_one' => '1 on 1 Guarding',
            'blocking' => 'Blocking',
            'team_defense' => 'Rotating / Positioning',
            'rebounding' => 'Rebounding',
        ],
        'intangibles' => [
            'effort' => 'Effort',
            'role_acceptance' => 'Role Acceptance',
            'iq' => 'I/Q',
            'awareness' => 'Awareness',
        ],
        'athleticism' => [
            'hands' => 'Hands',
            'length' => 'Length',
            'quickness' => 'Quickness',
            'jumping' => 'Jumping',
            'strength' => 'Strength',
            'coordination' => 'Coordination',
        ],
    ];

    protected $fillable = [
        'player_id',
        'user_id',
        'team_id_at_time',
        'game_id',
        'ratings',
        'notes',
        'synced_at',
    ];

    protected $casts = [
        'ratings' => 'array',
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the player this report is for.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Get the scout (user) who created this report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team the player belonged to when this report was created.
     */
    public function teamAtTime(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id_at_time');
    }

    /**
     * Get the game this report is associated with.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Check if this report has been synced to the server.
     */
    public function isSynced(): bool
    {
        return $this->synced_at !== null;
    }

    /**
     * Get a specific rating value.
     *
     * @param string $section e.g., 'offense'
     * @param string $subsection e.g., 'shooting'
     * @return int|null
     */
    public function getRating(string $section, string $subsection): ?int
    {
        return $this->ratings[$section][$subsection]['rating'] ?? null;
    }

    /**
     * Get notes for a specific subsection.
     *
     * @param string $section e.g., 'offense'
     * @param string $subsection e.g., 'shooting'
     * @return string|null
     */
    public function getSubsectionNotes(string $section, string $subsection): ?string
    {
        return $this->ratings[$section][$subsection]['notes'] ?? null;
    }

    /**
     * Set a rating value.
     *
     * @param string $section e.g., 'offense'
     * @param string $subsection e.g., 'shooting'
     * @param int|null $rating 1-5 or null
     * @return void
     */
    public function setRating(string $section, string $subsection, ?int $rating): void
    {
        $ratings = $this->ratings ?? [];

        if (!isset($ratings[$section])) {
            $ratings[$section] = [];
        }
        if (!isset($ratings[$section][$subsection])) {
            $ratings[$section][$subsection] = ['rating' => null, 'notes' => null];
        }

        $ratings[$section][$subsection]['rating'] = $rating;
        $this->ratings = $ratings;
    }

    /**
     * Set notes for a specific subsection.
     *
     * @param string $section e.g., 'offense'
     * @param string $subsection e.g., 'shooting'
     * @param string|null $notes
     * @return void
     */
    public function setSubsectionNotes(string $section, string $subsection, ?string $notes): void
    {
        $ratings = $this->ratings ?? [];

        if (!isset($ratings[$section])) {
            $ratings[$section] = [];
        }
        if (!isset($ratings[$section][$subsection])) {
            $ratings[$section][$subsection] = ['rating' => null, 'notes' => null];
        }

        $ratings[$section][$subsection]['notes'] = $notes;
        $this->ratings = $ratings;
    }

    /**
     * Get all ratings for a section.
     *
     * @param string $section e.g., 'offense'
     * @return array
     */
    public function getSectionRatings(string $section): array
    {
        return $this->ratings[$section] ?? [];
    }

    /**
     * Calculate the average rating for a specific section.
     *
     * @param string $section
     * @return float|null
     */
    public function getSectionAverage(string $section): ?float
    {
        $sectionData = $this->ratings[$section] ?? [];
        $ratings = [];

        foreach ($sectionData as $subsectionData) {
            if (isset($subsectionData['rating']) && $subsectionData['rating'] !== null) {
                $ratings[] = $subsectionData['rating'];
            }
        }

        return count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 2) : null;
    }

    /**
     * Calculate the overall average rating across all categories.
     */
    public function getAverageRatingAttribute(): ?float
    {
        if (!$this->ratings) {
            return null;
        }

        $allRatings = [];

        foreach ($this->ratings as $section => $subsections) {
            foreach ($subsections as $subsection => $data) {
                if (isset($data['rating']) && $data['rating'] !== null) {
                    $allRatings[] = $data['rating'];
                }
            }
        }

        return count($allRatings) > 0 ? round(array_sum($allRatings) / count($allRatings), 2) : null;
    }

    /**
     * Get the count of ratings that have been filled in.
     */
    public function getRatingsCountAttribute(): int
    {
        if (!$this->ratings) {
            return 0;
        }

        $count = 0;

        foreach ($this->ratings as $section => $subsections) {
            foreach ($subsections as $subsection => $data) {
                if (isset($data['rating']) && $data['rating'] !== null) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Get the total possible ratings count.
     */
    public function getTotalRatingsAttribute(): int
    {
        $total = 0;
        foreach (self::RATING_STRUCTURE as $subsections) {
            $total += count($subsections);
        }
        return $total;
    }

    /**
     * Check if the report is complete (all ratings filled).
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->ratings_count === $this->total_ratings;
    }

    /**
     * Get completion percentage.
     */
    public function getCompletionPercentageAttribute(): int
    {
        if ($this->total_ratings === 0) {
            return 0;
        }
        return (int) round(($this->ratings_count / $this->total_ratings) * 100);
    }

    /**
     * Initialize empty ratings structure.
     */
    public function initializeRatings(): void
    {
        $ratings = [];

        foreach (self::RATING_STRUCTURE as $section => $subsections) {
            $ratings[$section] = [];
            foreach ($subsections as $key => $label) {
                $ratings[$section][$key] = ['rating' => null, 'notes' => null];
            }
        }

        $this->ratings = $ratings;
    }
}
