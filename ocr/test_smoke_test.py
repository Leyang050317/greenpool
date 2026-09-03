import json
import unittest

from smoke_test import validate_payload


class SmokeContractTest(unittest.TestCase):
    def test_valid_recognition(self):
        validate_payload(json.dumps({"lines": [{"text": "GREENPOOL OCR TEST 12345"}]}))

    def test_rejects_empty_or_wrong_recognition(self):
        for payload in [{"lines": []}, {"lines": [{"text": "unrelated"}]}, {"lines": [None]}, []]:
            with self.subTest(payload=payload), self.assertRaises(ValueError):
                validate_payload(json.dumps(payload))

    def test_rejects_logging_mixed_into_json(self):
        with self.assertRaises(ValueError):
            validate_payload('Loading model...\n{"lines": []}')


if __name__ == "__main__":
    unittest.main()
