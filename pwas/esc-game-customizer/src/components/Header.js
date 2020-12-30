import React, { PureComponent, Fragment } from "react";
import { Box, Flex, FlexItem, PrimaryNav, NavItem, Button } from "mineral-ui";
import { Link } from "react-router-dom";
import { withRouter } from "react-router";
import { withTheme } from "emotion-theming";

import ActionButton from "./ActionButton";
import LoadingSpinner from "./LoadingSpinner";

import { ReactComponent as BrandingIcon } from "../icons/ic_branding.svg";
import { ReactComponent as ContentIcon } from "../icons/ic_content.svg";
import { ReactComponent as ExitArrowIcon } from "../icons/ic_exit_arrow.svg";
import { ReactComponent as CheckmarkIcon } from "../icons/ic_checkmark.svg";

const ThemedExitArrowIcon = withTheme(({ theme }) => (
  <ExitArrowIcon fill={theme.icon_color} />
));
const ThemedFlex = withTheme(({ theme, children }) => (
  <Flex
    css={{
      backgroundColor: "#1F2125"
    }}
  >
    {children}
  </Flex>
));
const saveButtonVariantMap = {
  [true]: "success",
  [false]: "danger",
  [undefined]: ""
};

class Header extends PureComponent {
  render() {
    const path = this.props.location.pathname;

    const {
      logo,
      onClickSave,
      onClickPublish,
      onClickSaveAndExit,
      onClickExit,
      saving = false,
      publishable = false,
      publishing = false,
      saveStatus = undefined
    } = this.props;

    const menuData = [
      {
        text: "Save and Exit",
        onClick: onClickSaveAndExit
      },
      {
        divider: true
      },
      {
        text: "Exit without saving",
        onClick: onClickExit
      }
    ];

    return (
      <ThemedFlex>
        <FlexItem width={1 / 2}>
          <PrimaryNav
            align="start"
            css={{
              padding: "0",
              alignItems: "center",
              height: "90px",
              backgroundColor: "transparent"
            }}
          >
            <FlexItem>
              <Box
                css={{ background: "rgba(0,0,0,.5)", cursor: "pointer" }}
                className={"game-logo"}
                padding="1rem"
                marginRight="2rem"
                onClick={onClickExit}
              >
                <div className={"game-logo-container"}>
                  {logo}
                  <ThemedExitArrowIcon />
                </div>
              </Box>
            </FlexItem>
            <NavItem
              as={Link}
              icon={
                <BrandingIcon
                  fill={path === "/branding" ? "#fff" : "#58606e"}
                />
              }
              selected={path === "/branding"}
              to="/branding"
              css={{
                height: "100%",
                borderRadius: 0,
                lineHeight: "95px",
                border: 0,
                borderBottom: `4px solid ${
                  path === "/branding" ? "#097CBE" : "transparent"
                }`
              }}
            >
              &nbsp; Branding
            </NavItem>
            <NavItem
              as={Link}
              icon={
                <ContentIcon fill={path === "/content" ? "#fff" : "#58606e"} />
              }
              selected={path === "/content"}
              to="/content"
              css={{
                height: "100%",
                borderRadius: 0,
                lineHeight: "95px",
                border: 0,
                borderBottom: `4px solid ${
                  path === "/content" ? "#097CBE" : "transparent"
                }`
              }}
            >
              &nbsp; Content
            </NavItem>
          </PrimaryNav>
        </FlexItem>
        <FlexItem width={1 / 2} alignSelf="center" marginRight="1rem">
          <Flex direction="column">
            <FlexItem alignSelf="end">
              <Box marginRight="1rem" inline>
                {publishing ? (
                  <Button onClick={e => e.preventDefault()}>
                    <LoadingSpinner
                      style={{ transform: "scale(.5) translateY(11px)" }}
                    />
                  </Button>
                ) : (
                  <Button
                    onClick={e => {
                      if (!publishable) {
                        return;
                      }
                      onClickPublish(e);
                    }}
                  >
                    <span style={{ color: "#FFF" }}>Publish</span>{" "}
                    {publishable ? (
                      <span style={{ color: "#06A7E0" }}>●</span>
                    ) : null}
                  </Button>
                )}
              </Box>
              <Box inline>
                {saving ? (
                  <ActionButton
                    primary
                    onClick={e => e.preventDefault()}
                    data={menuData}
                  >
                    <LoadingSpinner
                      style={{ transform: "scale(.5) translateY(11px)" }}
                    />
                  </ActionButton>
                ) : (
                  <ActionButton
                    primary
                    variant={saveButtonVariantMap[saveStatus]}
                    onClick={onClickSave}
                    data={menuData}
                  >
                    {saveStatus === true ? (
                      <Box inline marginTop="1rem">
                        <CheckmarkIcon />
                      </Box>
                    ) : saveStatus === false ? (
                      "Something went wrong"
                    ) : (
                      "Save"
                    )}
                  </ActionButton>
                )}
              </Box>
            </FlexItem>
          </Flex>
        </FlexItem>
      </ThemedFlex>
    );
  }
}

export default withRouter(Header);
