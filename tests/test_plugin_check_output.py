"""Do not confuse a clean Plugin Check response with an unparsable check."""
import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts'))
from plugin_check_output import parse_diagnostics


class PluginCheckOutputTest(unittest.TestCase):
    def test_clean_success(self):
        self.assertEqual([], parse_diagnostics('Success: Checks complete. No errors found.\n'))

    def test_file_diagnostics_are_preserved(self):
        self.assertEqual([{'type': 'WARNING'}, {'type': 'ERROR'}], parse_diagnostics(
            'FILE: plugin.php\n[{"type":"WARNING"}]\n\nFILE: includes/test.php\n[{"type":"ERROR"}]\n'))

    def test_json_array(self):
        self.assertEqual([{'code': 'warning'}], parse_diagnostics('[{"code":"warning"}]'))

    def test_unknown_or_mixed_output_fails(self):
        for output in ['', 'Success: something else', 'Warning: skipped\nSuccess: Checks complete. No errors found.',
                       'Success: Checks complete. No errors found.\n[{"type":"ERROR"}]', '{}', '[1]', 'not JSON']:
            with self.subTest(output=output), self.assertRaises(ValueError):
                parse_diagnostics(output)


if __name__ == '__main__':
    unittest.main()
