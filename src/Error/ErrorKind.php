<?php

declare(strict_types=1);

namespace ContaAzulCli\Error;

enum ErrorKind: string
{
    case ClientError = 'client_error';
    case Transient = 'transient';
    case Ambiguous = 'ambiguous';
    case AuthFailed = 'auth_failed';
    case RateLimited = 'rate_limited';
    case PollTimeoutKnownId = 'poll_timeout_known_id';
    case PollDropKnownId = 'poll_drop_known_id';
    case ServerError = 'server_error';
}
