class BootstrapPayload {
  const BootstrapPayload({
    required this.token,
    required this.tokenType,
    required this.user,
    required this.permissions,
    required this.company,
    required this.branches,
    required this.enabledModules,
    required this.homeScreen,
    required this.profile,
  });

  final String token;
  final String tokenType;
  final Map<String, dynamic> user;
  final List<String> permissions;
  final Map<String, dynamic>? company;
  final List<Map<String, dynamic>> branches;
  final List<String> enabledModules;
  final String homeScreen;
  final Map<String, dynamic> profile;

  static BootstrapPayload? tryParse(Map<String, dynamic> json) {
    final token = json['token'] as String?;
    if (token == null || token.isEmpty) return null;

    final user = json['user'];
    if (user is! Map) return null;

    final perms = json['permissions'];
    final permissions = perms is List
        ? perms.map((e) => e.toString()).toList()
        : <String>[];

    final company = json['company'];
    final branchesRaw = json['branches'];
    final branches = <Map<String, dynamic>>[];
    if (branchesRaw is List) {
      for (final b in branchesRaw) {
        if (b is Map<String, dynamic>) branches.add(b);
        if (b is Map) branches.add(Map<String, dynamic>.from(b));
      }
    }

    final em = json['enabled_modules'];
    final enabledModules =
        em is List ? em.map((e) => e.toString()).toList() : <String>[];

    final home = (json['home_screen'] ?? 'dashboard').toString();

    final prof = json['profile'];
    final profile = prof is Map<String, dynamic>
        ? prof
        : (prof is Map ? Map<String, dynamic>.from(prof) : <String, dynamic>{});

    return BootstrapPayload(
      token: token,
      tokenType: (json['token_type'] ?? 'Bearer').toString(),
      user: Map<String, dynamic>.from(user),
      permissions: permissions,
      company: company is Map<String, dynamic>
          ? company
          : (company is Map ? Map<String, dynamic>.from(company) : null),
      branches: branches,
      enabledModules: enabledModules,
      homeScreen: home,
      profile: profile,
    );
  }

  Map<String, dynamic> toStorageJson() => {
        'token': token,
        'token_type': tokenType,
        'user': user,
        'permissions': permissions,
        'company': company,
        'branches': branches,
        'enabled_modules': enabledModules,
        'home_screen': homeScreen,
        'profile': profile,
      };
}
