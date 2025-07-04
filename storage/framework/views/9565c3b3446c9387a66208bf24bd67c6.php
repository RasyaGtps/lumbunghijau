<div
    <?php echo e($attributes
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)); ?>

>
    <?php echo e($getChildComponentContainer()); ?>

</div>
<?php /**PATH C:\lumbunghijau\backend\vendor\filament\infolists\resources\views\components\group.blade.php ENDPATH**/ ?>