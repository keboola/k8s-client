<?php

declare(strict_types=1);

namespace Keboola\K8sClient\Model\Io\Keboola\Apps\V2;

use KubernetesRuntime\AbstractModel;

/**
 * AppDevSpec carries dev-mode-only knobs. Ignored when AppSpec.mode != "dev".
 */
class AppDevSpec extends AbstractModel
{
    /**
     * GitPollInterval is how often the in-pod git-watcher fetches and resets
     * to origin/<branch>. ISO-8601 duration string (e.g. "1s", "30s", "5m").
     * Default 1s, min 1s, max 5m (enforced at admission via CRD CEL).
     *
     * @var string|null
     */
    public $gitPollInterval = null;

    /**
     * AutoRunSetupOnDepChange controls whether the in-pod git-watcher runs
     * setup-dev.sh and restarts the app program when a tracked dependency
     * file changes during a poll cycle. Default true.
     *
     * @var bool|null
     */
    public $autoRunSetupOnDepChange = null;
}
