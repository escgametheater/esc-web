import React, { PureComponent, Fragment } from "react";
import {
  Button,
  Flex,
  Box,
  FlexItem,
  Card,
  CardBlock,
  CardDivider,
  Text,
  Link
} from "mineral-ui";

import ColorPicker from "../components/ColorPicker";
import PreviewController from "../components/PreviewController";
import Cropper from "../components/Cropper";
import FileUploadButton from "../components/FileUploadButton";
import VTTPlayer from "../components/VTTPlayer";

import "./Branding.css";

function readFile(file) {
  return new Promise((s, r) => {
    // Read the image, and remove it
    const reader = new FileReader();
    reader.onload = e => {
      s(e.target.result);
    };

    reader.readAsDataURL(file);
  });
}

class BrandingGroup extends PureComponent {
  render() {
    const { groupDescription, groupLabel, children } = this.props;

    return (
      <Fragment>
        <Text as="h1" noMargins={!!groupDescription}>
          {groupLabel}
        </Text>
        {groupDescription && <Text as="h6">{groupDescription}</Text>}
        {children}
      </Fragment>
    );
  }
}

const vttCommands = {
  SetControllerPhase(parameters, previewController) {
    console.log("SetControllerPhase called ", parameters);
    previewController.postMessage({
      simulateMessage: true,
      type: "UC_GameState",
      body: {
        ...parameters
      }
    });
  }
};

export default class Branding extends PureComponent {
  constructor(props) {
    super(props);
    this.state = {};
  }
  static getDerivedStateFromProps(props, state) {
    const newState = {
      ...state
    };
    let anyChanged = false;

    props.fields.forEach(field => {
      newState[field.name] = field.value;

      if (field.type === "ESCCustomImageAssetField") {
        newState[`${field.name}-filename`] =
          field.properties.file_meta.filename;
      }

      if (!state) {
        anyChanged = true;
      } else if (!anyChanged && newState[field.name] !== state[field.name]) {
        anyChanged = true;
      }
    });

    if (anyChanged) {
      return newState;
    }

    return null;
  }

  handleColorChange = field => color => {
    this.setState({
      [field.name]: color
    }, this.updatePreviewTheme);

    this.props.onFieldChange(field, color);
  };

  handleFileChange = field => e => {
    const file = e.target.files[0];

    // Right type?
    if (
      !file.type.startsWith("image/") ||
      (!~file.type.indexOf("png") &&
        !~file.type.indexOf("jpg") &&
        !~file.type.indexOf("jpeg"))
    ) {
      console.error("Not an image!");
      return;
    }

    // Right size?
    if (file.size >= 128000) {
      console.error("To big!", file.size);
      // return;
    }

    readFile(file).then(data => {
      this.setState({
        [`${field.name}-image-data`]: data,
        [field.name]: file.name,
        imageData: data
      });

      console.log(`${field.name}-image-data  CHANGED`, data.length);

      this.props.onFieldChange(field, file);
    });
  };

  handleImageChange = field => ({ base64, blob }) => {
    console.log("IMAGE CHANGE ... ", base64.length);
    this.setState({
      [`${field.name}-image-data-mutated`]: base64
    }, this.updatePreviewTheme);

    this.props.onFieldChange(field, blob);
  };

  findFieldNameByNamePart = namePart =>
    Object.keys(this.state).find(n => !!~n.indexOf(namePart));

  findFieldByNamePart = namePart => {
    const field = this.findFieldNameByNamePart(namePart);
    return this.state[field];
  };

  _memoizePreview = el => (this._preview = el);

  handlePreviewControllerReady = () => {
    // Set initial state of controller
    console.log("###", this.state.cues[0]);
    this.updatePreviewTheme();
    console.log("player: ", this.state.player);
    const promise = this.state.player.play();
    if(promise) {
      promise.catch((e) => {
        console.log("unable to play...");
        this.state.player.play();
      })
    }
  };
  updatePreviewTheme = () => {
    const primaryColor = this.findFieldByNamePart("primaryColor");
    const backgroundColor = this.findFieldByNamePart("backgroundColor");
    const poweredByLogo = this.state[
      this.findFieldNameByNamePart("poweredByLogo") + "-image-data-mutated"
    ];

    if (!this.state.cues || !this.state.cues.length) {
      console.info("No cues loaded, not updating theme");
      return;
    }

    console.log("Branding:: updatePreviewTheme ", primaryColor, backgroundColor, poweredByLogo && poweredByLogo.length)

    vttCommands.SetControllerPhase(
      {
        ...this.state.cues[0].data.parameters,
        isPlaying: true,
        gameData: {
          ...this.state.cues[0].data.parameters.gameData,
          brandDefinitions: [
            {
              brandingId: -1,
              primaryColor,
              backgroundColor,
              poweredByLogo
            }
          ]
        }
      },
      this._preview
    );    
  }
  
  handleSwapColors = (field1, field2) => {
    const color1 = this.state[field1];
    const color2 = this.state[field2];
    const field1Model = this.props.fields.find(f => f.name === field1);
    const field2Model = this.props.fields.find(f => f.name === field2);


    this.handleColorChange(field1Model)(color2);
    this.handleColorChange(field2Model)(color1);

    console.log("handleSwapColors", field1, field2, color1, color2, this.handleColorChange);
  }

  render() {
    const { fields, gameControllers, gameModBuild, gamePhases, gameBuild } = this.props;

    const playerController = gameControllers.find(
      gc => gc.game_controller_type.slug === "player"
    );

    const primaryColor = this.findFieldByNamePart("primaryColor");
    document.documentElement.style.setProperty(
      "--cropper--backgroundColor",
      primaryColor
    );

    let vttData = this.props.vttData && this.props.vttData[playerController.game_controller_type.slug];

    return (
      <Card>
        <CardBlock>
          <Flex>
            <FlexItem width={2 / 3}>
              <BrandingGroup groupLabel="Color Palette">
                <Flex>
                  {fields
                    .filter(f => f.field_group === "branding")
                    .filter(f => f.type === "HexaDecimalColorField")
                    .map((field, i) => (
                      <FlexItem width={1 / 2} key={`color-field-${i}`}>
                        <Text as="h2" appearance="h4" noMargins>
                          {field.label}
                        </Text>
                        <Text as="h6">{field.help_text}</Text>
                        <ColorPicker
                          name={field.name}
                          color={this.state[field.name] || "#000000"}
                          onChange={this.handleColorChange(field)}
                          swapWith={!!~field.name.indexOf("primaryColor") && this.findFieldNameByNamePart("backgroundColor")}
                          onSwapClick={swapWithField => this.handleSwapColors(field.name, swapWithField)}
                        />
                        {!!~field.name.indexOf("primaryColor") && (
                          <Box marginTop="2rem">
                            <Text>
                              Need a reference?{" "}
                              <Link
                                href="https://teamcolorcodes.com"
                                target="__blank"
                              >
                                teamcolorcodes.com
                              </Link>
                            </Text>
                          </Box>
                        )}
                      </FlexItem>
                    ))}
                </Flex>
              </BrandingGroup>
              <CardDivider />
              <BrandingGroup
                groupLabel="Hosting Brand Logo"
                groupDescription={`Typically your brand or venue primary logo, accompanied by text that will read "Presented By"`}
              >
                <Flex>
                  {fields
                    .filter(f => f.field_group === "branding")
                    .filter(f => f.type === "ESCCustomImageAssetField")
                    .map((field, i) => {
                      const fieldAsset = gameModBuild.custom_game_assets.find(
                        cga => cga.slug === field.value
                      );

                      const width = field.properties.aspectX * 256;
                      const height = (field.properties.aspectX / (field.properties.aspectY || 1)) * 256;

                      return (
                        <Fragment key={`custom-image-field-${i}`}>
                          <FlexItem width={1 / 3}>
                            <Cropper
                              style={{
                                width: width,
                                height: height
                              }}
                              viewport={{
                                width: width,
                                height: height
                              }}
                              data={
                                !!this.state[`${field.name}-image-data`]
                                  ? this.state[`${field.name}-image-data`]
                                  : fieldAsset
                                  ? fieldAsset.public_url
                                  : null
                              }
                              onUpdate={this.handleImageChange(field)}
                              properties={field.properties}
                            />
                          </FlexItem>
                          <FlexItem width={2 / 3} marginLeft="2em">
                            <Text>{this.state[`${field.name}-filename`]}</Text>
                            <Box marginBottom="1rem">
                              <FileUploadButton
                                accept=".png,.jpg,.jpeg"
                                onChange={this.handleFileChange(field)}
                              >
                                Upload
                              </FileUploadButton>
                            </Box>
                            <Text noMargins>
                              {field.help_text}
                            </Text>
                          </FlexItem>
                        </Fragment>
                      );
                    })}
                </Flex>
              </BrandingGroup>
            </FlexItem>
            <FlexItem width={1 / 3}>
              <Flex
                width={375}
                marginLeft="auto"
                marginRight="auto"
                marginBottom="1rem"
              >
                <FlexItem>
                  <Text as="h6">Preview:</Text>
                </FlexItem>
                <FlexItem width={2 / 3}>
                  <Text as="h3" align="center">
                    {this.state.currentCue
                      ? this.state.currentCue.parameters.phase
                      : "Waiting"}
                  </Text>
                  <VTTPlayer
                    vttData={vttData}
                    onCuesLoaded={async (cues, el) => {
                      this.setState({ cues, player: el});
                    }}
                    onCueEnter={(cue, i) => {
                      this.setState({
                        currentCueIndex: i,
                        currentCue: cue
                      });

                      console.log("Cue enter", cue);
                      if (!this._preview) {
                        return;
                      }

                      const { type, method, parameters } = cue;

                      parameters._publishTime = new Date().getTime();

                      if (type === "call" && vttCommands[method]) {
                        console.log(
                          "Branding:: call " + method + "::",
                          parameters,
                          cue
                        );
                        vttCommands[method](parameters, this._preview);
                      }
                    }}
                    loop
                  />
                </FlexItem>
              </Flex>
              <PreviewController
                style={{
                  margin: "0 auto"
                }}
                src={playerController.url}
                ref={this._memoizePreview}
                onFrameLoad={this.handlePreviewControllerReady}
              />
            </FlexItem>
          </Flex>
        </CardBlock>
      </Card>
    );
  }
}
