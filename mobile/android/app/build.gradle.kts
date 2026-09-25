import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Release signing is read from android/key.properties (never committed).
val keyProperties = Properties().apply {
    val file = rootProject.file("key.properties")
    if (file.exists()) file.inputStream().use { load(it) }
}

android {
    namespace = "ir.gamyar.app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        // flutter_local_notifications uses java.time on API < 26 paths.
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // Pushe manifest token (console → app → manifest token), from PUSHE_TOKEN or -PpusheToken.
        manifestPlaceholders["appLinkHost"] = System.getenv("APP_LINK_HOST") ?: (project.findProperty("appLinkHost") as String? ?: "gamyar.ir")
        manifestPlaceholders["pusheToken"] = System.getenv("PUSHE_TOKEN") ?: (project.findProperty("pusheToken") as String? ?: "")
        applicationId = "ir.gamyar.app"
        // Android 8.0+: hardware-backed EC keys and a sane step-counter/background model.
        minSdk = 26
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    // One build per store. Same application id everywhere; the flavor only tells
    // the app (via Flutter's appFlavor) where it is distributed, which decides
    // Play Integrity vs. hardware key attestation and FCM vs. Pushe.
    flavorDimensions += "store"
    // JVM tests render the widgets with Robolectric native graphics (see StepWidgetRenderTest).
    testOptions {
        unitTests.isIncludeAndroidResources = true
    }

    productFlavors {
        create("play") { dimension = "store" }
        create("bazaar") { dimension = "store" }
        create("myket") { dimension = "store" }
    }
    // Pushe (Iranian push service) ships only in the Bazaar/Myket builds; Play uses FCM.
    sourceSets {
        getByName("bazaar").java.srcDir("src/pushe/kotlin")
        getByName("myket").java.srcDir("src/pushe/kotlin")
        getByName("play").java.srcDir("src/nopushe/kotlin")
        getByName("bazaar").manifest.srcFile("src/pushe/AndroidManifest.xml")
        getByName("myket").manifest.srcFile("src/pushe/AndroidManifest.xml")
    }

    signingConfigs {
        if (keyProperties.isNotEmpty()) {
            create("release") {
                storeFile = file(keyProperties.getProperty("storeFile"))
                storePassword = keyProperties.getProperty("storePassword")
                keyAlias = keyProperties.getProperty("keyAlias")
                keyPassword = keyProperties.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.findByName("release") ?: signingConfigs.getByName("debug")
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.5")
    implementation("com.google.android.play:integrity:1.4.0")
    implementation("com.google.android.gms:play-services-location:21.3.0")
    implementation("androidx.work:work-runtime:2.10.0")
    implementation("androidx.core:core-ktx:1.15.0")
    // Play install referrer: carries the invite code through a Play Store install.
    implementation("com.android.installreferrer:installreferrer:2.2")
    testImplementation("junit:junit:4.13.2")
    testImplementation("org.robolectric:robolectric:4.16")
    testImplementation("androidx.test:core:1.6.1")
    "bazaarImplementation"("co.pushe.plus:base:2.6.4")
    "myketImplementation"("co.pushe.plus:base:2.6.4")
}

flutter {
    source = "../.."
}

// ./gradlew :app:testBazaarDebugUnitTest -DwidgetShots=<dir> writes the widget renders as PNGs.
tasks.withType<Test>().configureEach {
    System.getProperty("widgetShots")?.let { systemProperty("widgetShots", it) }
    // Robolectric fetches its Android runtime jar at test time: reuse the build's proxy/truststore.
    listOf("https.proxyHost", "https.proxyPort", "http.proxyHost", "http.proxyPort", "http.nonProxyHosts",
        "javax.net.ssl.trustStore", "javax.net.ssl.trustStoreType").forEach { key ->
        System.getProperty(key)?.let { systemProperty(key, it) }
    }
}

// Flutter's asset copy isn't wired into AGP's host-test packaging; declare it for each variant.
tasks.matching { it.name.startsWith("package") && it.name.endsWith("UnitTestForUnitTest") }.configureEach {
    val variant = name.removePrefix("package").removeSuffix("UnitTestForUnitTest")
    dependsOn("copyFlutterAssets$variant")
}

// Robolectric's Android runtime, resolved by Gradle (cached, proxy-aware) instead of at test time.
val robolectricRuntime: Configuration by configurations.creating
dependencies {
    robolectricRuntime("org.robolectric:android-all-instrumented:14-robolectric-10818077-i7")
}
val robolectricJars = layout.buildDirectory.dir("robolectric-jars")
val copyRobolectricRuntime by tasks.registering(Copy::class) {
    from(robolectricRuntime)
    into(robolectricJars)
}
tasks.withType<Test>().configureEach {
    dependsOn(copyRobolectricRuntime)
    systemProperty("robolectric.offline", "true")
    systemProperty("robolectric.dependency.dir", robolectricJars.get().asFile.absolutePath)
}
