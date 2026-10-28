# Teamones framework maintenance

## 3.0.14 - 2026-10-28

- Serialize log-directory creation, size checks, rotation and writes across cooperating worker processes using a stable directory lock outside rotated files.
- Use unique archive names to retain multiple rotations within one second; preserve the existing message format and configuration interface.
- Keep PHP 8.5/E_ALL diagnostics active. No warning suppression or vendor-only patch.
- Focused regression: 3 cases / 202 assertions, including 6 workers writing 180 unique records. The concurrency test requires Linux with pcntl.
