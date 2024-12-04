<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
  public function getFilters(): array
  {
	return [
	  new TwigFilter('pluralize', [$this, 'pluralize'])
	];
  }

  public function pluralize(int $count, string $singular, string $plural, string $zero = null): string
  {
	if ($count > 1){
	  return str_replace('{}', $count, $plural);
	} else if ($count <= 0 && null !== $zero){
	  return $zero; // No string replacement required for zero
	}
	return str_replace('{}', $count, $singular);
  }
}



