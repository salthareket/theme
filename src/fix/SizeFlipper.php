<?php
namespace PrestaShop\RtlCss\Transformation\Operation;

use Sabberworm\CSS\Value\Size;

/**
 * Flips sizes
 */
class SizeFlipper
{
    /**
     * Inverts a size by multiplying it by -1
     *
     * @param Size $size
     *
     * @return Size Flipped size
     */
    /*public function invertSize(Size $size)
    {
        $scalarSize = $size->getSize();
        if ($scalarSize === 0.0) {
            return $size;
        }

        return new Size(-1 * $scalarSize, $size->getUnit(), $size->isColorComponent());
    }*/
    public function invertSize($size) {
        if ($size instanceof \Sabberworm\CSS\Value\CalcFunction) {
            // CalcFunction için özel işlem
            foreach ($size->getArguments() as $arg) {
                if ($arg instanceof \Sabberworm\CSS\Value\Size) {
                    $arg->setSize(-$arg->getSize()); // setValue yerine setSize kullanıldı
                }
            }
            return $size;
        } elseif ($size instanceof \Sabberworm\CSS\Value\Size) {
            // Normal Size işlemi
            $size->setSize(-$size->getSize()); // setValue yerine setSize kullanıldı
            return $size;
        } else {
            throw new \InvalidArgumentException('Unsupported size type');
        }
    }


}
