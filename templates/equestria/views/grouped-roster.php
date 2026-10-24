<?php
/** @var list<array> $groups */
$e = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="staff-roster" data-grouped-roster="<?= $e($refreshUrl) ?>">
<?php if ($groups===[]): ?><p>Lista administracji jest jeszcze pusta.</p><?php endif; ?>
<?php foreach ($groups as $group): ?>
<section class="staff-roster-group" aria-label="<?= $e($group['label']) ?>">
    <h2><?= $e($group['label']) ?></h2>
    <ul class="staff-roster-list">
    <?php foreach ($group['members'] as $member): ?>
        <li class="staff-roster-member">
            <div class="staff-roster-identity">
            <?php if ($member['head_url'] !== null): ?>
                <img class="staff-roster-head" src="<?= $e($member['head_url']) ?>" alt="" width="40" height="40" loading="lazy">
            <?php else: ?><span class="staff-roster-head staff-roster-placeholder" aria-label="Brak skina w profilu">?</span><?php endif; ?>
            <a class="staff-roster-player" href="<?= $e($member['player_url']??$member['url']??'#') ?>"><span class="staff-roster-rank"><?php
                if ($member['rank_format'] === []) echo $e($member['rank']);
                else foreach ($member['rank_format'] as $run) {
                    $classes = ['profile-prefix-part'];
                    foreach (['bold','italic','underlined','strikethrough'] as $decoration) if ($run[$decoration] ?? false) $classes[] = 'is-'.$decoration;
                    $color = $run['color'] ?? null;
                    $style = is_string($color) && preg_match('/^#[a-fA-F0-9]{6}$/D', $color) ? ' style="color:'.$e($color).'"' : '';
                    echo '<span class="'.$e(implode(' ', $classes)).'"'.$style.'>'.$e($run['text']).'</span>';
                }
                ?></span> <strong><?= $e($member['name']) ?></strong></a>
            </div>
            <div class="staff-roster-details">
            <?php if ($member['responsibilities']!==''): ?><p><?= $e($member['responsibilities']) ?></p><?php endif; ?>
            <?php if (($member['bio']??'')!==''): ?><p class="staff-roster-bio"><?= $e($member['bio']) ?></p><?php endif; ?>
            </div>
            <div class="staff-roster-roles" aria-label="Dodatkowe role">
            <?php foreach ($member['roles'] as $role): ?><span><?= $e($role) ?></span><?php endforeach; ?>
            <?php if (($member['team_url']??null)!==null): ?><a href="<?= $e($member['team_url']) ?>">Team</a><?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>
</div>
