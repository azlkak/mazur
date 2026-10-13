"""Run the real POSIX shell runner against disposable PHP process boundaries."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

RUNNER = Path(__file__).resolve().parents[1] / 'server/mls/private/bin/run-import.sh'
DOMAIN_ROOT = '/home/u101822986/domains/mazurestate.pl/mls'


class CronRunnerTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='mazur cron runner ', dir=RUNNER.parent)
        self.root = Path(self.temp.name).resolve()
        self.cwd = self.root / 'unrelated working directory'
        self.cwd.mkdir()
        self.calls = self.root / 'php calls.jsonl'
        self.php = self.root / 'fake php'
        self.php.write_text('''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
with open(os.environ['RUNNER_TEST_CALL_LOG'], 'a') as log:
    log.write(json.dumps({'argv': sys.argv[1:], 'cwd': os.getcwd()}) + '\\n')
script = Path(sys.argv[1]).name
if script == 'import.php':
    sys.exit(int(os.environ['RUNNER_TEST_IMPORT_EXIT']))
if script == 'translate-offers.php':
    sys.exit(int(os.environ['RUNNER_TEST_SYNC_EXIT']))
if script == 'check-integrations.php':
    sys.exit(int(os.environ['RUNNER_TEST_MONITOR_EXIT']))
sys.exit(99)
''')
        self.php.chmod(0o700)
        self.feed = self.root / 'private feed directory'
        self.runner = self.root / 'run import.sh'
        # Only substitute deployment paths; run the unchanged shell control flow.
        self.runner.write_text(RUNNER.read_text().replace('/usr/bin/php', str(self.php)).replace(DOMAIN_ROOT, str(self.feed)))

    def tearDown(self):
        self.temp.cleanup()

    def run_runner(self, args=(), import_exit=0, sync_exit=0, monitor_exit=0):
        environment = dict(os.environ)
        environment.update({
            'RUNNER_TEST_CALL_LOG': str(self.calls),
            'RUNNER_TEST_IMPORT_EXIT': str(import_exit),
            'RUNNER_TEST_SYNC_EXIT': str(sync_exit),
            'RUNNER_TEST_MONITOR_EXIT': str(monitor_exit),
        })
        result = subprocess.run(['/bin/sh', str(self.runner), *args], cwd=self.cwd,
                                env=environment, capture_output=True, text=True)
        calls = [json.loads(line) for line in self.calls.read_text().splitlines()] if self.calls.exists() else []
        return result, calls

    def assert_calls(self, calls, esti=False):
        expected_import = [str(self.feed / 'bin/import.php')]
        if esti:
            expected_import.append('--source=esticrm')
        self.assertEqual([call['argv'] for call in calls], [expected_import, [str(self.feed / 'bin/check-integrations.php')], [str(self.feed / 'bin/translate-offers.php'), '--sync']])
        self.assertEqual([call['cwd'] for call in calls], [str(self.cwd)] * 3)

    def test_monitor_failure_does_not_block_sync_or_successful_import(self):
        result, calls = self.run_runner(monitor_exit=3)
        self.assertEqual(result.returncode, 0)
        self.assert_calls(calls)
        self.assertIn('integration monitor failed: exit=3', result.stderr)

    def test_monitor_failure_preserves_import_failure(self):
        result, calls = self.run_runner(import_exit=4, monitor_exit=3)
        self.assertEqual(result.returncode, 4)
        self.assert_calls(calls)

    def test_default_mls_uses_absolute_quoted_paths_from_another_cwd(self):
        result, calls = self.run_runner()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assert_calls(calls)
        self.assertIn('source=mls import_exit=0 sync_exit=0', result.stdout)

    def test_explicit_mls(self):
        result, calls = self.run_runner(('mls',))
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assert_calls(calls)

    def test_esticrm_only_adds_its_source_argument(self):
        result, calls = self.run_runner(('esticrm',))
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assert_calls(calls, esti=True)

    def test_import_failure_still_runs_sync_and_returns_failure(self):
        result, calls = self.run_runner(import_exit=4)
        self.assertEqual(result.returncode, 4)
        self.assert_calls(calls)
        self.assertIn('import_exit=4 sync_exit=0', result.stdout)

    def test_sync_failure_is_propagated(self):
        result, calls = self.run_runner(sync_exit=7)
        self.assertEqual(result.returncode, 7)
        self.assert_calls(calls)

    def test_both_failures_preserve_import_error_and_do_not_skip_sync(self):
        result, calls = self.run_runner(('esticrm',), import_exit=4, sync_exit=7)
        self.assertEqual(result.returncode, 4)
        self.assert_calls(calls, esti=True)
        self.assertIn('import_exit=4 sync_exit=7', result.stdout)

    def test_maximum_shell_exit_status_does_not_become_success(self):
        result, calls = self.run_runner(import_exit=255)
        self.assertEqual(result.returncode, 255)
        self.assert_calls(calls)

    def test_invalid_sources_and_extra_arguments_never_start_php(self):
        for args in [('ru',), ('',), ('--source=esticrm',), ('mls', 'extra'), ('esticrm; echo unsafe',)]:
            with self.subTest(args=args):
                result, calls = self.run_runner(args)
                self.assertEqual(result.returncode, 2)
                self.assertEqual(calls, [])
                self.assertIn('Usage:', result.stderr)


if __name__ == '__main__':
    unittest.main(verbosity=2)
