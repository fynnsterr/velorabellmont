<?php

$labels = [1 => 'Cart', 2 => 'Delivery and payment', 3 => 'Confirmation'];
?>
<div class="wrap">
    <ol class="steps" aria-label="Checkout progress">
        <?php foreach ($labels as $n => $label): ?>
            <li class="step<?= $n < $step ? ' is-done' : '' ?><?= $n === $step ? ' is-current' : '' ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>>
                <span class="step-num"><?= $n < $step ? '&#10003;' : $n ?></span>
                <span class="step-label"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
