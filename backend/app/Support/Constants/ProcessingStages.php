<?php

namespace App\Support\Constants;

final class ProcessingStages
{
    public const QUEUED = 'queued';

    public const DOWNLOAD = 'download';

    public const PARSE = 'parse';

    public const VALIDATE = 'validate';

    public const NORMALIZE = 'normalize';

    public const TRANSLATE = 'translate';

    public const WRITE_DOCX = 'write_docx';

    public const BUILD_HTML = 'build_html';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';
}
