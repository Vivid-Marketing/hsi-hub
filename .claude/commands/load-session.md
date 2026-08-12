List all session notes in .notes/ and let me pick one to load.

1. Run `ls -t .notes/*.md 2>/dev/null` to get all notes files sorted by most recent first.

2. If no files exist, say so and suggest running /save-session at the end of a session first.

3. If files exist, display a numbered list showing:
   - The number
   - The filename (without path)
   - The first H1 title from inside the file
   - The date from the frontmatter

   Example:
   1. 2026-07-09-eb-health-check-fix.md — EB Health Check & Cache Fix
   2. 2026-06-15-video-hub.md — Video Hub Implementation

4. Ask which number to load.

5. Once the user picks one, read the full file and say: "Loaded. Here's where we left off:" followed by a brief recap of the status and next steps from that note so we can pick up immediately.
