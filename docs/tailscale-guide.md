# Tailscale guide: registering the server and devices (Personal plan)

How the Icon Venue & Suites booking system is kept to **registered devices only**. The site works only on devices signed
in to our Tailscale network ("tailnet") **and** allowed by its access rules.
- Why it's built this way: [decision](decisions/2026-11-06-tailnet-only-access.md).
- Technical runbook: [deployment.md](deployment.md).

| Who | Sections |
|---|---|
| **Admin** (tailnet owner, `innoendo.itsolutions@gmail.com`) | 1–4, 6, 7 |
| **Staff member** (registering a phone or PC) | 5 |

**Plan limits (Personal, free):**
- Up to **6 users**, each with unlimited devices, plus up to 50 tagged servers.
- Tailscale describes the plan as non-commercial; we chose it knowingly.
- A 7th user would need the Standard plan ($8/user/month).
- Source: [pricing](https://tailscale.com/pricing).

---

## 1. One-time tailnet setup (admin)
1. Sign in at https://login.tailscale.com with the admin Google account. A Gmail sign-in creates a **Personal**
   tailnet.
2. **DNS** page: turn on **MagicDNS**, then **HTTPS Certificates**.
   - The server's name (`iconvenue`) will appear in public certificate logs, which is normal.
3. **Settings → Device management:** turn on **Device approval**. It's available on all plans
   ([docs](https://tailscale.com/kb/1099/device-approval)). From then on, every new device waits for you to approve it.
   This is what makes access "registered devices only".
4. **Settings → User management:** turn on **user approval**, if offered, so invited people also need your OK.
5. **Access controls:** replace the whole policy with the one below.
   - It removes the default "allow all" grant (`{"src": ["*"], "dst": ["*"], "ip": ["*"]}`).
   - It gives the booking website to **every member of the tailnet** (`autogroup:member`), so there's no email list to
     maintain.
   - **Only invite people who should use the booking system.** To lend one device to someone else, use **Share** on
     the Machines page instead of an invite.
   ```jsonc
   // Icon Venue & Suites: only approved tailnet members' devices can reach the booking system.
   {
   	// Only tailnet admins can put the server tag on a machine.
   	"tagOwners": {
   		"tag:iconvenue-prod": ["autogroup:admin"],
   	},

   	"grants": [
   		// The booking website for every member of the tailnet (anyone you've invited and approved).
   		{"src": ["autogroup:member"], "dst": ["tag:iconvenue-prod"], "ip": ["tcp:443"]},
   		// Server maintenance (Tailscale SSH) for admins only.
   		{"src": ["autogroup:admin"], "dst": ["tag:iconvenue-prod"], "ip": ["tcp:22"]},
   		// Each person's own devices can still reach each other, not anyone else's.
   		{"src": ["autogroup:member"], "dst": ["autogroup:self"], "ip": ["*"]},
   	],

   	"ssh": [
   		{"action": "check", "src": ["autogroup:admin"],  "dst": ["tag:iconvenue-prod"], "users": ["autogroup:nonroot", "root"]},
   		{"action": "check", "src": ["autogroup:member"], "dst": ["autogroup:self"],     "users": ["autogroup:nonroot", "root"]},
   	],

   	// Saving is refused if the admin would lose access to the server.
   	"tests": [
   		{"src": "innoendo.itsolutions@gmail.com", "accept": ["tag:iconvenue-prod:443", "tag:iconvenue-prod:22"]},
   	],
   }
   ```
   - `tcp:443` is the booking website, for every tailnet member.
   - `tcp:22` is server maintenance, for admins only.
   - A device opens the app only if **(a)** its user was invited and approved, and **(b)** the device itself is approved.
     That's the "registered devices only" rule.
   - Use the login shown on the **Users** page in `tests` if it differs from the address above.

## 2. Register the server (admin, once)
The server is the Google Cloud VM `iconvenue-db`. It is the only way into the app.

1. The setup script is run for you. From the repo:
   ```bash
   gcloud compute ssh iconvenue-db --zone us-central1-a --project icon-events-513902 --tunnel-through-iap \
     --command 'sudo bash -s' < deploy/provision-gateway.sh
   ```
2. It prints a line like `To authenticate, visit: https://login.tailscale.com/a/…`. **Open that link as the admin and
   approve.**
   - No auth key is created or stored.
   - The server joins as **`iconvenue`** with the tag **`tag:iconvenue-prod`**. Tagged servers don't expire.
3. **Machines** page: check that `iconvenue` shows the tag `tag:iconvenue-prod`. Approve it if it says "Needs approval".
4. The script finishes by printing the app address, `https://iconvenue.tailf0e670.ts.net`. Note it down: this is
   the **only** address staff use.

To re-register later (for example, after rebuilding the VM): remove the old `iconvenue` machine on the Machines page,
then run step 1 again.

## 3. Invite a staff member (admin)
1. **Users** page → **Invite external users**. You can send an **email invite**, or **copy an invite link** and send it
   yourself.
   - Role: **Member**.
   - One invite per person. Invites are single-use and expire after 30 days.
   - Staff can sign in with Google, Microsoft, Apple, or a passkey.
   - Source: [invite any user](https://tailscale.com/docs/features/sharing/how-to/invite-any-user).
2. There's nothing to change in Access controls: as a tailnet member they get the website automatically.
3. Once they've accepted and installed Tailscale (section 5), **approve their user (if asked) and their device(s)**.
   Section 4 covers device approval.

Staff also need an **app account** (email + password inside the booking system). Admins create it under
**Admin → Staff**. The two are separate:
- **Tailscale** decides which *devices* can reach the site.
- The **app login** decides what the *person* can do.

## 4. Approve a new device (admin)
1. **Machines** page. New devices appear at the top with **Needs approval**
   ([docs](https://tailscale.com/kb/1099/device-approval)).
2. Check that the user and device name are expected (for example, "Ana's iPhone").
3. Open **⋯ → Approve**. The device connects right away.
4. If it's a shared front-desk PC, consider **⋯ → Disable key expiry**. Otherwise it signs out after the default expiry
   (180 days) and needs a re-login.

## 5. Register your device (staff)
You need: the invite from the admin, and a phone or computer you'll use for work.

1. **Accept the invite.** Open the email or link from the admin and sign in with the account it was sent to.
2. **Install Tailscale:**
   - iPhone/iPad: App Store → "Tailscale".
   - Android: Play Store → "Tailscale".
   - Windows or Mac: https://tailscale.com/download.
3. Open Tailscale and **sign in with the same account**.
   - **Mac:** use the Tailscale icon in the **menu bar** → **Log in…**. When macOS asks to add a VPN configuration,
     click **Allow**. If it asks for a system/network extension, enable Tailscale under **System Settings → General →
     Login Items & Extensions → Network Extensions**.
   - Installed but "can't connect"? The app is usually just **logged out**: open it and log in.
   - If it asks which network, pick the admin's (the tailnet you were invited to).
4. Turn the Tailscale switch **on** (Connected). The admin then approves your device. Until they do, the app shows
   "Needs approval" / waiting.
5. In your browser, open **`https://iconvenue.tailf0e670.ts.net`**, then sign in
   with your booking-system email and password.
6. Bookmark it. Tailscale must be **on** whenever you use the site: if it's off, the page won't load.

**Tips**
- Lost or replaced a phone? Tell the admin so they can remove the old device.
- Don't install Tailscale on personal devices you share with others.

## 6. Remove access (admin)
| To | Do this |
|---|---|
| Remove one device (lost phone, old PC) | **Machines** → device → **⋯ → Remove** |
| Remove a person completely | **Users** → their user → **Remove user** (their devices are removed with them). Also deactivate their app account under **Admin → Staff** |
| Temporarily block a person | **Users** → their user → **Suspend user** (if offered), or remove their devices on the Machines page. Re-approve later |

Changes take effect within seconds.

## 7. Troubleshooting
| Symptom | Likely cause | Fix |
|---|---|---|
| "This site can't be reached" / name not found | Tailscale is off, stopped or logged out, or MagicDNS is off | Open Tailscale and make sure it says **Connected** (log in if needed); admin checks DNS → MagicDNS |
| Installed Tailscale on a Mac but it won't connect | Logged out, or macOS blocked the VPN configuration or network extension | Menu bar → Log in; allow the VPN configuration and the network extension; admin approves the Mac |
| Page times out, but Tailscale is on | Device or user not approved yet, or signed in to a different tailnet | Admin approves the user and device; check the Tailscale app shows the admin's tailnet |
| Worked before, now doesn't | Device key expired (re-login needed), or removed | Open Tailscale and sign in again; admin re-approves if needed |
| Opens, but "These credentials do not match" | Tailscale works; it's the app password | Reset the app password under Admin → Staff |
| "Too many login attempts" | 5 wrong passwords within a minute | Wait a minute and try again |
| Everyone is locked out at once | The server `iconvenue` is offline | Admin checks Machines → `iconvenue` is Connected; see [deployment.md](deployment.md) |
| Opening the old `…run.app` address gives 404 | Expected: it's closed to the internet | Use the ts.net address |

## 8. What clients see
Clients **can't** open the website anymore; it's internal. Staff check availability and prices for them by phone, email
or social media (see [business rules](BUSINESS_RULES.md), core process step 1).
