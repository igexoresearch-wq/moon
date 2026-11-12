<?php
declare(strict_types=1);
/** Canonical mission time from the simulation's one-second tick counter. */
function moonMetFormat(int $tick):string {
 if($tick<0)throw new InvalidArgumentException('Negative mission elapsed time');
 return sprintf('%03d:%02d:%02d',intdiv($tick,3600),intdiv($tick%3600,60),$tick%60);
}
