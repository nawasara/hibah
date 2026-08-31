<?php

declare(strict_types=1);

use Nawasara\Hibah\Search\ApprovedProposalSearchProvider;

/*
 * Penyedia hasil palet ⌘K dari nawasara/hibah.
 *
 * Hasilnya tersaring per OPD secara otomatis (ScopedToOpd), dan tautannya
 * mengarah ke menu yang benar sesuai peruntukan tiap baris.
 */
return [
    ApprovedProposalSearchProvider::class,
];
