<?php

use App\Support\Search;

it('escapes LIKE wildcards so the term matches literally', function () {
    expect(Search::pattern('50%_done!'))->toBe('%50!%!_done!!%');
});
